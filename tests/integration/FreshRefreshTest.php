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

use Carbon\Carbon;
use Flarum\User\User;
use FoF\OnlineUsers\Tests\Support\FakeCacheAdapter;
use FoF\OnlineUsers\UserRepository;

/**
 * A realtime membership event means the list has probably changed, but the
 * cached answer can be up to `cache_ttl` old. The client therefore asks for a
 * *fresh* answer after such an event.
 *
 * Fresh requests are not unbounded cache misses: they share a short window, so
 * however many subscribers react to one event (or however often a client asks),
 * the database is queried at most once per window per audience.
 */
class FreshRefreshTest extends OnlineUsersTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_fresh_request_recomputes_while_a_normal_one_is_still_served_from_cache()
    {
        $this->prepareDatabase([User::class => [$this->user(2, null), $this->user(10, 1), $this->user(11, null)]]);

        $repository = $this->repository(new FakeCacheAdapter());
        $actor = $this->actor(2);

        $this->assertEquals([10], $this->ids($repository->getOnlineUsers($actor)['users']));

        // 11 comes online after the entry was cached.
        $this->database()->table('users')->where('id', 11)->update(['last_seen_at' => Carbon::now()->toDateTimeString()]);

        $this->assertEquals([10], $this->ids($repository->getOnlineUsers($actor)['users']), 'a normal request keeps the cache');
        $this->assertEqualsCanonicalizing([10, 11], $this->ids($repository->getOnlineUsers($actor, true)['users']));
    }

    public function test_fresh_requests_share_a_window_and_recompute_once_it_has_passed()
    {
        Carbon::setTestNow('2026-01-01 12:00:00');

        $this->prepareDatabase([User::class => [$this->user(2, null), $this->user(10, 1), $this->user(11, null)]]);

        $cache = new FakeCacheAdapter();
        $repository = $this->repository($cache);
        $actor = $this->actor(2);

        $repository->getOnlineUsers($actor, true);
        $this->assertEquals(1, $cache->misses);

        $this->database()->table('users')->where('id', 11)->update(['last_seen_at' => Carbon::now()->toDateTimeString()]);

        Carbon::setTestNow('2026-01-01 12:00:01');
        $this->assertEquals([10], $this->ids($repository->getOnlineUsers($actor, true)['users']), 'same window: shared');
        $this->assertEquals(1, $cache->misses);

        // The window is no longer than the client's minimum delay after an event,
        // so a refetch triggered by an event can never land in a window that
        // started before the event.
        $this->assertEquals(2, UserRepository::FRESH_WINDOW);

        Carbon::setTestNow('2026-01-01 12:00:02');
        $this->assertEqualsCanonicalizing([10, 11], $this->ids($repository->getOnlineUsers($actor, true)['users']));
        $this->assertEquals(2, $cache->misses);
    }

    public function test_fresh_results_do_not_leak_into_the_normal_cache_entry()
    {
        $this->prepareDatabase([User::class => [$this->user(2, null), $this->user(10, 1)]]);

        $cache = new FakeCacheAdapter();
        $repository = $this->repository($cache);
        $actor = $this->actor(2);

        $repository->getOnlineUsers($actor, true);
        $repository->getOnlineUsers($actor);

        $this->assertEquals(2, $cache->misses);
    }
}
