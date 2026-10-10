<?php

/*
 * This file is part of fof/online-users-widget.
 *
 * Copyright (c) 2021 Friends of Flarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\OnlineUsers\Tests\integration;

use Flarum\Group\Group;
use Flarum\User\User;
use FoF\OnlineUsers\Tests\Support\FakeCacheAdapter;

/**
 * Clicking an avatar in the widget opens that user's profile. Core's profile page
 * reuses any user already in the store that has `joinTime`, and only fetches
 * `/users/{slug}` when there is none — so the online users have to arrive with
 * everything the profile renders from the store, or it renders without it until
 * a refresh. The group badges come from the `groups` relationship.
 *
 * It must also cost nothing per user: `groups` is read from the loaded relation,
 * so without eager loading each online user is a query of their own. The query
 * tests below pin that, and the rest of what a request costs.
 */
class OnlineUserGroupsTest extends OnlineUsersTestCase
{
    protected const VIP = 5;
    protected const HIDDEN = 6;

    /** @var array<array{sql: string, bindings: array}> */
    protected array $queries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->grant('viewOnlineUsersWidget', Group::MEMBER_ID);

        // Caching would make the second of two requests cheaper than the first and
        // hide what is being measured.
        $this->setting('fof-online-users-widget.cache_ttl', 0);
    }

    /**
     * An ordinary member, plus `$count` online users. Every third is a moderator,
     * every second a VIP, and every fifth is in a hidden group; every one of
     * them is in at least one group, except the last.
     */
    protected function seedOnlineUsers(int $count, int $first = 10): void
    {
        $users = [$this->user(2, null)];
        $memberships = [];

        for ($i = 0; $i < $count; $i++) {
            $id = $first + $i;
            $users[] = $this->user($id, 1);

            if ($i % 3 === 0) {
                $memberships[] = ['user_id' => $id, 'group_id' => Group::MODERATOR_ID];
            }
            if ($i % 2 === 0) {
                $memberships[] = ['user_id' => $id, 'group_id' => self::VIP];
            }
            if ($i % 5 === 0) {
                $memberships[] = ['user_id' => $id, 'group_id' => self::HIDDEN];
            }
        }

        $this->prepareDatabase([
            'groups' => [
                ['id' => self::VIP, 'name_singular' => 'VIP', 'name_plural' => 'VIPs', 'color' => '#ff0000', 'icon' => 'fas fa-star', 'is_hidden' => 0],
                ['id' => self::HIDDEN, 'name_singular' => 'Secret', 'name_plural' => 'Secrets', 'color' => null, 'icon' => null, 'is_hidden' => 1],
            ],
            User::class => $users,
            'group_user' => $memberships,
        ]);
    }

    protected function forumDocument(int $actorId = 2, array $query = []): array
    {
        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => $actorId])->withQueryParams($query));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Group ids linked from each online user, keyed by user id.
     *
     * @return array<string, string[]>
     */
    protected function groupsByUser(array $document): array
    {
        $online = array_column($document['data']['relationships']['onlineUsers']['data'], 'id');
        $byId = [];

        foreach ($document['included'] ?? [] as $resource) {
            // The actor's own request can mark them as online too; they are not what is under test.
            if ($resource['type'] === 'users' && $resource['id'] !== '2' && in_array($resource['id'], $online, true)) {
                $this->assertArrayHasKey('groups', $resource['relationships'] ?? [], "user {$resource['id']} carries no groups relationship");
                $this->assertArrayHasKey('data', $resource['relationships']['groups'], "user {$resource['id']} carries no groups linkage");

                $ids = array_column($resource['relationships']['groups']['data'], 'id');
                sort($ids);
                $byId[$resource['id']] = $ids;
            }
        }

        ksort($byId);

        return $byId;
    }

    /**
     * Run `$request`, returning the queries it issued.
     *
     * @return array<array{sql: string, bindings: array}>
     */
    protected function queriesDuring(callable $request): array
    {
        $this->queries = [];
        $capturing = true;

        $this->database()->listen(function ($query) use (&$capturing) {
            if ($capturing) {
                $this->queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
            }
        });

        $request();
        $capturing = false;

        return $this->queries;
    }

    public function test_the_boot_payload_carries_each_online_users_groups()
    {
        $this->seedOnlineUsers(4);

        $groups = $this->groupsByUser($this->forumDocument());

        $this->assertEquals([
            '10' => ['4', '5'],  // moderator and VIP; the hidden group is not shown
            '11' => [],          // in none, which is still linkage: an empty list, not absent
            '12' => ['5'],
            '13' => ['4'],
        ], $groups);
    }

    public function test_a_refetch_carries_the_groups_too()
    {
        $this->seedOnlineUsers(4);

        $document = $this->forumDocument(2, ['include' => 'onlineUsers,onlineUsers.groups']);

        $this->assertCount(4, $this->groupsByUser($document));
    }

    public function test_hidden_groups_are_not_exposed_to_actors_who_cannot_see_them()
    {
        $this->seedOnlineUsers(1);

        $this->assertNotContains((string) self::HIDDEN, $this->groupsByUser($this->forumDocument(2))['10']);
    }

    public function test_hidden_groups_are_shown_to_actors_who_can_see_them()
    {
        $this->seedOnlineUsers(1);

        $this->assertContains((string) self::HIDDEN, $this->groupsByUser($this->forumDocument(1))['10']);
    }

    public function test_queries_do_not_grow_with_the_number_of_online_users()
    {
        $this->seedOnlineUsers(3);
        $this->forumDocument(); // the first request pays one-off boot queries
        $few = count($this->queriesDuring(fn () => $this->forumDocument()));

        // Same request, many more users, all with groups.
        $this->seedMore(12);
        $many = count($this->queriesDuring(fn () => $this->forumDocument()));

        $this->assertEquals($few, $many, "3 online users took $few queries, 15 took $many");
    }

    public function test_the_refetch_does_not_grow_either()
    {
        $this->seedOnlineUsers(3);
        $query = ['include' => 'onlineUsers,onlineUsers.groups'];

        $this->forumDocument(2, $query); // the first request pays one-off boot queries
        $few = count($this->queriesDuring(fn () => $this->forumDocument(2, $query)));

        $this->seedMore(12);
        $many = count($this->queriesDuring(fn () => $this->forumDocument(2, $query)));

        $this->assertEquals($few, $many, "3 online users took $few queries, 15 took $many");
    }

    public function test_each_widget_query_runs_once_per_request()
    {
        $this->seedOnlineUsers(8);

        $queries = $this->queriesDuring(fn () => $this->forumDocument());

        $sqlOf = array_column($queries, 'sql');
        $widget = fn (string $needle) => count(array_filter($sqlOf, fn ($sql) => str_contains($sql, $needle)));

        // The total and the list are two fields over one result.
        $this->assertEquals(1, $widget('count(*) as `aggregate` from `users` where `last_seen_at`'), 'the count');
        $this->assertEquals(1, $widget('select `id` from `users` where'), 'the ids');

        // One load of the groups for all eight online users together, as opposed to the
        // actor's own groups, which are a lookup for a single id. Eloquent inlines the
        // integer ids of an eager load, so they are counted in the IN list.
        $forMany = array_filter($sqlOf, fn ($sql) => preg_match('/`group_user`\.`user_id` in \((?:\d+, ){7,}\d+\)/', $sql));
        $this->assertCount(1, $forMany, 'the groups of the online users, loaded together');
    }

    public function test_an_actor_without_the_permission_costs_no_widget_queries()
    {
        $this->seedOnlineUsers(8);
        $this->app();
        $this->database()->table('group_permission')->where('permission', 'viewOnlineUsersWidget')->delete();

        $sql = array_column($this->queriesDuring(fn () => $this->forumDocument()), 'sql');

        $this->assertEmpty(array_filter($sql, fn ($q) => str_contains($q, '`last_seen_at` >')), 'no online users query');
        $this->assertEmpty(array_filter($sql, fn ($q) => str_contains($q, '`group_user`.`user_id` in')), 'no groups load for the online users');
    }

    public function test_a_cache_hit_costs_only_loading_the_users_and_their_groups()
    {
        $this->seedOnlineUsers(8);

        $repository = $this->repository(new FakeCacheAdapter());
        $actor = $this->actor(2);

        $repository->getOnlineUsers($actor); // fills the cache

        $sql = array_column($this->queriesDuring(fn () => $repository->getOnlineUsers($actor)), 'sql');

        $this->assertEmpty(array_filter($sql, fn ($q) => str_contains($q, '`last_seen_at` >')), 'the count and the ids come from the cache');
        $this->assertCount(2, $sql, 'the users, and their groups: '.implode("\n", $sql));
    }

    /**
     * More online users, added after the first request.
     */
    protected function seedMore(int $count): void
    {
        $first = 100;

        for ($i = 0; $i < $count; $i++) {
            $id = $first + $i;

            $this->database()->table('users')->insert([
                'id' => $id,
                'username' => "more$id",
                'email' => "more$id@machine.local",
                'password' => 'x',
                'is_email_confirmed' => 1,
                'preferences' => json_encode([]),
                'last_seen_at' => date('Y-m-d H:i:s'),
            ]);

            $this->database()->table('group_user')->insert(['user_id' => $id, 'group_id' => $i % 2 ? Group::MODERATOR_ID : self::VIP]);
        }
    }
}
