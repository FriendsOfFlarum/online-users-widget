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

use FoF\ForumWidgets\SafeCacheRepositoryAdapter;
use Carbon\Carbon;
use Flarum\User\User;
use Flarum\Settings\SettingsRepositoryInterface;

class UserRepository
{
    /**
     * @var array<callable(User): string>
     */
    protected static $cacheKeyParameters = [];

    public static function addCacheKeyParameter(callable $parameter)
    {
        static::$cacheKeyParameters[] = $parameter;
    }

    public function __construct(protected SettingsRepositoryInterface $settings, protected SafeCacheRepositoryAdapter $cache)
    {
    }

    protected function cacheKey(User $actor): string
    {
        $params = [
            $actor->hasPermission('user.viewLastSeenAt') ? 'high-access' : 'low-access',
            $this->settings->get('fof-online-users-widget.max_users'),
            $this->settings->get('fof-online-users-widget.cache_ttl'),
            $this->settings->get('fof-online-users-widget.last_seen_interval')
        ];

        foreach (self::$cacheKeyParameters as $parameter) {
            $params[] = $parameter($actor);
        }

        $rand = '002';

        return 'fof-online-users-widget.users-'.$rand.'-' . md5(implode('-', $params));
    }

    public function getLastSeenUsers(User $actor): array
    {
        $limit = (int) $this->settings->get('fof-online-users-widget.max_users');
        $ttl = (int) $this->settings->get('fof-online-users-widget.cache_ttl');
        $interval = (int) $this->settings->get('fof-online-users-widget.last_seen_interval');

        return $this->cache->remember($this->cacheKey($actor), $ttl, function () use ($actor, $limit, $interval) {
            $query = User::query()
                ->select('id', 'preferences')
                ->whereVisibleTo($actor)
                ->where('last_seen_at', '>', Carbon::now()->subMinutes($interval));

            $count = $query->count();

            $users = $query
                ->limit($limit)
                ->get();

            // user.viewLastSeenAt is a permission that allows viewing online state
            // regardless of the privacy preference not to be shown.
            if (! $actor->hasPermission('user.viewLastSeenAt')) {
                $users = $users->filter(function (User $user) {
                    return (bool) $user->getPreference('discloseOnline');
                });
            }

            return [
                'users' => $users->pluck('id')->toArray(),
                'count' => $count
            ];
        }) ?: [];
    }

    public function getOnlineUsers(User $actor): array
    {
        $online = $this->getLastSeenUsers($actor);

        return [
            'users' => User::query()->whereIn('id', $online['users'])->get(),
            'count' => $online['count']
        ];
    }
}
