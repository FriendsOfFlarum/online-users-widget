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
 * What a request costs, and that it does not grow with the number of online users.
 *
 * The online users are serialized by core's user resource, so anything it reads per
 * user (a relation, a policy that checks `isAdmin()`) is a query per online user
 * unless something loads it for all of them at once. The fixtures put the users in
 * groups, which is what such a read would hit. `flarum/testing` also fails any
 * request that repeats one query shape per record; these tests pin the totals on top
 * of that, since the detector's threshold hides small counts.
 */
class QueryCostTest extends OnlineUsersTestCase
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
     * The SQL without the parts that differ between the supported setups, so one
     * needle matches on all of them: MySQL quotes identifiers with backticks, the
     * others with double quotes, and a configured table prefix is part of every
     * table name.
     */
    protected function plainSql(string $sql): string
    {
        $sql = str_replace(['`', '"'], '', $sql);
        $prefix = $this->database()->getTablePrefix();

        // Only where it starts an identifier, so a short prefix such as `p_` is not
        // also removed from the inside of `group_user`.
        return $prefix === '' ? $sql : preg_replace('/(?<![A-Za-z0-9_])'.preg_quote($prefix, '/').'(?=[A-Za-z_])/', '', $sql);
    }

    /**
     * How many of `$queries` are the widget's own: the count, the ids, or the
     * loading of the users it found.
     *
     * @param array<array{sql: string, bindings: array}> $queries
     */
    protected function widgetQueries(array $queries): int
    {
        return count(array_filter(array_column($queries, 'sql'), fn (string $sql) => str_contains($sql, 'from users where last_seen_at')));
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
                $this->queries[] = ['sql' => $this->plainSql($query->sql), 'bindings' => $query->bindings];
            }
        });

        $request();
        $capturing = false;

        return $this->queries;
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
        $query = ['include' => 'onlineUsers'];

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
        $this->assertEquals(1, $widget('count(*) as aggregate from users where last_seen_at'), 'the count');
        $this->assertEquals(1, $widget('select id from users where last_seen_at'), 'the ids');
        $this->assertEquals(1, $widget('from users where id in'), 'the users, loaded together');
    }

    public function test_an_actor_with_the_permission_costs_the_widget_queries()
    {
        $this->seedOnlineUsers(8);

        // The control for the test below: the widget's queries are recognised on this
        // driver, so finding none there means something rather than the matching
        // having quietly stopped working.
        $this->assertSame(2, $this->widgetQueries($this->queriesDuring(fn () => $this->forumDocument())), 'the count and the ids');
    }

    public function test_an_actor_without_the_permission_costs_no_widget_queries()
    {
        $this->seedOnlineUsers(8);

        // Revoked before the first request, and in a test of its own: core keeps a
        // static cache of permissions per set of groups, which deleting rows directly
        // does not invalidate, so the same app would still see the permission.
        $this->app();
        $this->database()->table('group_permission')->where('permission', 'viewOnlineUsersWidget')->delete();

        $queries = $this->queriesDuring(fn () => $this->forumDocument());

        $this->assertSame(0, $this->widgetQueries($queries), 'no online users query');
        $this->assertEmpty(array_filter(array_column($queries, 'sql'), fn ($q) => preg_match('/from users where id in \((?:\?, ){7,}\?\)/', $q)), 'no loading of the online users');
    }

    public function test_a_cache_hit_costs_only_loading_the_users()
    {
        $this->seedOnlineUsers(8);

        $repository = $this->repository(new FakeCacheAdapter());
        $actor = $this->actor(2);

        // Control: the first call is the miss, and issues the count and the ids.
        $this->assertSame(2, $this->widgetQueries($this->queriesDuring(fn () => $repository->getOnlineUsers($actor))));

        $hit = $this->queriesDuring(fn () => $repository->getOnlineUsers($actor));

        $this->assertSame(0, $this->widgetQueries($hit), 'the count and the ids come from the cache');
        $this->assertCount(1, $hit, 'just the users: '.implode("\n", array_column($hit, 'sql')));
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
