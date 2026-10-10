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

class OnlineUsersListTest extends OnlineUsersTestCase
{
    /**
     * An ordinary member who is not themselves online, so they never appear in
     * the results they are asking for.
     */
    protected function member(): array
    {
        return $this->user(2, null);
    }

    public function test_online_means_seen_within_the_interval()
    {
        $this->setting('fof-online-users-widget.last_seen_interval', 5);

        $this->prepareDatabase([
            User::class => [
                $this->member(),
                $this->user(10, 4),
                $this->user(11, 6),
                $this->user(12, null),
            ],
        ]);

        $result = $this->repository()->getOnlineUsers($this->actor(2));

        $this->assertEquals([10], $this->ids($result['users']));
        $this->assertEquals(1, $result['count']);
    }

    public function test_the_cap_limits_the_list_but_not_the_count_and_keeps_most_recent_first()
    {
        $this->setting('fof-online-users-widget.max_users', 2);

        $this->prepareDatabase([
            User::class => [
                $this->member(),
                $this->user(10, 4),
                $this->user(11, 1),
                $this->user(12, 3),
                $this->user(13, 2),
            ],
        ]);

        $result = $this->repository()->getOnlineUsers($this->actor(2));

        $this->assertEquals(4, $result['count']);
        $this->assertEquals([11, 13], $this->ids($result['users']));
    }

    public function test_opted_out_users_are_hidden_from_the_list_and_the_count()
    {
        $this->prepareDatabase([
            User::class => [
                $this->member(),
                $this->user(10, 1, ['preferences' => ['discloseOnline' => false]]),
                $this->user(11, 1, ['preferences' => ['discloseOnline' => true]]),
                $this->user(12, 1, ['preferences' => []]),
            ],
        ]);

        // The factory cannot produce a NULL preferences column, and seeding the
        // raw `users` table alongside `User::class` would overwrite the factory
        // rows, so insert this one directly once the app has booted.
        $this->app();
        $this->database()->table('users')->insert([
            'id' => 13,
            'username' => 'user13',
            'email' => 'user13@machine.local',
            'password' => 'x',
            'is_email_confirmed' => 1,
            'preferences' => null,
            'last_seen_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->repository()->getOnlineUsers($this->actor(2));

        $this->assertEqualsCanonicalizing([11, 12, 13], $this->ids($result['users']));
        $this->assertEquals(3, $result['count']);
    }

    public function test_actors_who_can_view_last_seen_at_also_see_opted_out_users()
    {
        $this->grant('user.viewLastSeenAt', Group::MEMBER_ID);

        $this->prepareDatabase([
            User::class => [
                $this->member(),
                $this->user(10, 1, ['preferences' => ['discloseOnline' => false]]),
                $this->user(11, 1),
            ],
        ]);

        $result = $this->repository()->getOnlineUsers($this->actor(2));

        $this->assertEqualsCanonicalizing([10, 11], $this->ids($result['users']));
        $this->assertEquals(2, $result['count']);
    }

    public function test_the_result_is_cached_between_calls()
    {
        $this->prepareDatabase([User::class => [$this->member(), $this->user(10, 1)]]);

        $cache = new FakeCacheAdapter();
        $repository = $this->repository($cache);

        $repository->getOnlineUsers($this->actor(2));
        $repository->getOnlineUsers($this->actor(2));

        $this->assertEquals(1, $cache->misses);
    }

    public function test_an_unusable_cache_still_yields_the_real_result()
    {
        $this->prepareDatabase([User::class => [$this->member(), $this->user(10, 1), $this->user(11, 1)]]);

        $result = $this->repository(new FakeCacheAdapter(false))->getOnlineUsers($this->actor(2));

        $this->assertEqualsCanonicalizing([10, 11], $this->ids($result['users']));
        $this->assertEquals(2, $result['count']);
    }

    public function test_a_cleared_interval_setting_falls_back_to_the_default_rather_than_nobody_online()
    {
        $this->setting('fof-online-users-widget.last_seen_interval', '');

        $this->prepareDatabase([User::class => [$this->member(), $this->user(10, 1)]]);

        $result = $this->repository()->getOnlineUsers($this->actor(2));

        $this->assertEquals([10], $this->ids($result['users']));
    }

    public function test_a_zero_cap_still_lists_someone()
    {
        $this->setting('fof-online-users-widget.max_users', 0);

        $this->prepareDatabase([User::class => [$this->member(), $this->user(10, 1), $this->user(11, 1)]]);

        $result = $this->repository()->getOnlineUsers($this->actor(2));

        $this->assertNotEmpty($result['users']);
        $this->assertEquals(2, $result['count']);
    }
}
