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

use Flarum\User\User;
use FoF\OnlineUsers\Tests\Support\FakeCacheAdapter;

/**
 * The cached result is only valid for actors who can see the same set of users.
 *
 * Core's user visibility scope shows everyone to anyone who can `viewForum`.
 * Without it, a guest sees nobody and a member sees only themselves — so on a
 * private forum the visible set differs per actor, and a result cached for one
 * actor must never be served to another.
 */
class CacheScopeTest extends OnlineUsersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            User::class => [
                $this->user(2),
                $this->user(3),
                $this->user(4),
            ],
        ]);
    }

    public function test_on_a_private_forum_members_only_ever_see_themselves()
    {
        $this->makeForumPrivate();

        $repository = $this->repository(new FakeCacheAdapter());

        $first = $repository->getOnlineUsers($this->actor(2));
        $second = $repository->getOnlineUsers($this->actor(3));

        $this->assertEquals([2], $this->ids($first['users']));
        $this->assertEquals([3], $this->ids($second['users']));
    }

    public function test_a_members_count_is_not_served_to_a_guest_on_a_private_forum()
    {
        $this->makeForumPrivate();

        $repository = $this->repository(new FakeCacheAdapter());

        $repository->getOnlineUsers($this->actor(2));
        $guest = $repository->getOnlineUsers($this->actor(null));

        $this->assertEquals(0, $guest['count']);
        $this->assertSame([], $guest['users']);
    }

    public function test_a_guests_empty_result_is_not_served_to_a_member_on_a_private_forum()
    {
        $this->makeForumPrivate();

        $repository = $this->repository(new FakeCacheAdapter());

        $repository->getOnlineUsers($this->actor(null));
        $member = $repository->getOnlineUsers($this->actor(2));

        $this->assertEquals([2], $this->ids($member['users']));
        $this->assertEquals(1, $member['count']);
    }

    public function test_on_a_public_forum_actors_still_share_one_cache_entry()
    {
        $cache = new FakeCacheAdapter();
        $repository = $this->repository($cache);

        $repository->getOnlineUsers($this->actor(2));
        $repository->getOnlineUsers($this->actor(3));
        $repository->getOnlineUsers($this->actor(null));

        $this->assertEquals(1, $cache->misses);
    }
}
