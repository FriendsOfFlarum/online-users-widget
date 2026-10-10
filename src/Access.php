<?php

/*
 * This file is part of fof/online-users-widget.
 *
 * Copyright (c) 2021 Friends of Flarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\OnlineUsers;

use Flarum\User\User;

class Access
{
    /**
     * Whether the actor may be shown the online users and their count.
     *
     * Enforced where the data is serialized, not only by the widget hiding
     * itself: `onlineUsers` is a default include on the forum resource, so it
     * reaches every actor's `GET /api` and the page payload.
     */
    public static function canView(User $actor): bool
    {
        return $actor->hasPermission('viewOnlineUsersWidget');
    }

    /**
     * Whether the actor may join the `presence-online` channel.
     *
     * Joining announces the actor, by display name, to every other subscriber,
     * so it discloses that they are online. Someone who has opted out via
     * `discloseOnline` therefore does not join (and gets no live updates).
     *
     * `user.viewLastSeenAt` is the exception: it lets its holders see everyone's
     * online state regardless of preference, so it is not withheld from them.
     */
    public static function canJoinPresence(User $actor): bool
    {
        return static::canView($actor)
            && ($actor->getPreference('discloseOnline') || $actor->hasPermission('user.viewLastSeenAt'));
    }
}
