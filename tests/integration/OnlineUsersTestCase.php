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
use Flarum\Group\Group;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\TestCase;
use Flarum\User\Guest;
use Flarum\User\User;
use FoF\ForumWidgets\SafeCacheRepositoryAdapter;
use FoF\OnlineUsers\Tests\Support\FakeCacheAdapter;
use FoF\OnlineUsers\UserRepository;

abstract class OnlineUsersTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-forum-widgets-core', 'fof-online-users-widget');
    }

    /**
     * A user row for `prepareDatabase([User::class => [...]])`.
     *
     * @param int|null $minutesAgo minutes since last seen, or null for never
     */
    protected function user(int $id, ?int $minutesAgo = 1, array $attributes = []): array
    {
        return array_merge([
            'id' => $id,
            'username' => "user$id",
            'email' => "user$id@machine.local",
            'last_seen_at' => $minutesAgo === null ? null : Carbon::now()->subMinutes($minutesAgo)->toDateTimeString(),
        ], $attributes);
    }

    protected function grant(string $permission, int $groupId): void
    {
        $this->prepareDatabase(['group_permission' => [['group_id' => $groupId, 'permission' => $permission]]]);
    }

    /**
     * Flarum seeds `viewForum` for guests. Removing it, from guests and members
     * alike, reproduces a private forum — the case where the core user
     * visibility scope collapses to "nothing" for guests and "yourself" for
     * members.
     */
    protected function makeForumPrivate(): void
    {
        $this->app();

        $this->database()->table('group_permission')
            ->where('permission', 'viewForum')
            ->whereIn('group_id', [Group::GUEST_ID, Group::MEMBER_ID])
            ->delete();
    }

    protected function actor(?int $id): User
    {
        $this->app();

        return $id === null ? new Guest() : User::query()->findOrFail($id);
    }

    protected function repository(?SafeCacheRepositoryAdapter $cache = null): UserRepository
    {
        return new UserRepository(
            $this->app()->getContainer()->make(SettingsRepositoryInterface::class),
            $cache ?? new FakeCacheAdapter()
        );
    }

    /**
     * @return int[]
     */
    protected function ids(array $users): array
    {
        return array_map(fn (User $user) => (int) $user->id, $users);
    }
}
