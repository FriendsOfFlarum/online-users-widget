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
use FoF\OnlineUsers\Access;

/**
 * Subscribing to `presence-online` announces the subscriber, by display name,
 * to every other subscriber — so joining is itself a disclosure of being online.
 * A user who opted out via `discloseOnline` must therefore not join, unless they
 * hold `user.viewLastSeenAt`, the permission that lets them see everyone's online
 * state regardless of preference.
 */
class PresenceGuardTest extends OnlineUsersTestCase
{
    public function test_user_without_the_widget_permission_cannot_join()
    {
        $this->prepareDatabase([User::class => [$this->user(2)]]);

        $this->assertFalse(Access::canJoinPresence($this->actor(2)));
    }

    public function test_user_with_the_permission_and_default_preference_can_join()
    {
        $this->grant('viewOnlineUsersWidget', Group::MEMBER_ID);
        $this->prepareDatabase([User::class => [$this->user(2)]]);

        $this->assertTrue(Access::canJoinPresence($this->actor(2)));
    }

    public function test_user_who_explicitly_discloses_can_join()
    {
        $this->grant('viewOnlineUsersWidget', Group::MEMBER_ID);
        $this->prepareDatabase([User::class => [$this->user(2, 1, ['preferences' => ['discloseOnline' => true]])]]);

        $this->assertTrue(Access::canJoinPresence($this->actor(2)));
    }

    public function test_opted_out_user_cannot_join()
    {
        $this->grant('viewOnlineUsersWidget', Group::MEMBER_ID);
        $this->prepareDatabase([User::class => [$this->user(2, 1, ['preferences' => ['discloseOnline' => false]])]]);

        $this->assertFalse(Access::canJoinPresence($this->actor(2)));
    }

    public function test_opted_out_user_who_can_always_see_online_status_can_still_join()
    {
        $this->grant('viewOnlineUsersWidget', Group::MEMBER_ID);
        $this->grant('user.viewLastSeenAt', Group::MEMBER_ID);
        $this->prepareDatabase([User::class => [$this->user(2, 1, ['preferences' => ['discloseOnline' => false]])]]);

        $this->assertTrue(Access::canJoinPresence($this->actor(2)));
    }

    public function test_view_last_seen_at_alone_does_not_grant_the_widget_permission()
    {
        $this->grant('user.viewLastSeenAt', Group::MEMBER_ID);
        $this->prepareDatabase([User::class => [$this->user(2)]]);

        $this->assertFalse(Access::canJoinPresence($this->actor(2)));
    }
}
