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

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use FoF\ForumWidgets\SafeCacheRepositoryAdapter;
use Illuminate\Database\Eloquent\Builder;

class UserRepository
{
    public const DEFAULT_MAX_USERS = 15;
    public const DEFAULT_CACHE_TTL = 30;
    public const DEFAULT_LAST_SEEN_INTERVAL = 5;

    /**
     * @var array<callable(User): string>
     */
    protected static array $cacheKeyParameters = [];

    public static function addCacheKeyParameter(callable $parameter): void
    {
        static::$cacheKeyParameters[] = $parameter;
    }

    public function __construct(protected SettingsRepositoryInterface $settings, protected SafeCacheRepositoryAdapter $cache)
    {
    }

    /**
     * An integer setting, falling back to its default when it is blank or not a
     * number (an admin clearing the field stores ''), and never below `$min`.
     */
    protected function intSetting(string $key, int $default, int $min): int
    {
        $value = $this->settings->get($key);

        return is_numeric($value) ? max($min, (int) $value) : $default;
    }

    /**
     * Which users the actor can see, as far as the cached result is concerned.
     *
     * Core's user visibility scope shows everyone to anyone who can `viewForum`.
     * Without it a guest sees nobody and a member sees only themselves, so the
     * visible set is then specific to the actor and cannot be shared.
     */
    protected function visibilityScope(User $actor): string
    {
        if ($actor->can('viewForum')) {
            return 'forum';
        }

        return $actor->isGuest() ? 'none' : 'self-'.$actor->id;
    }

    protected function cacheKey(User $actor, int $limit, int $ttl, int $interval): string
    {
        $params = [
            $this->visibilityScope($actor),
            $actor->hasPermission('user.viewLastSeenAt') ? 'high-access' : 'low-access',
            $limit,
            $ttl,
            $interval,
        ];

        foreach (static::$cacheKeyParameters as $parameter) {
            $params[] = $parameter($actor);
        }

        return 'fof-online-users-widget.users-'.md5(implode('-', $params));
    }

    /**
     * Resolve the users to show as online, plus the total before the
     * `max_users` cap is applied.
     *
     * "Online" means `last_seen_at` within `last_seen_interval`. This is
     * deliberately the only definition: realtime's `presence-online` roster is
     * an exact list of live sockets, but it is also the *subscriber* list, and
     * subscribing requires `viewOnlineUsersWidget` — so on any forum that
     * restricts that permission the roster is a small subset of who is actually
     * online, and using it would shrink the list rather than sharpen it.
     * Realtime instead refetches this result when membership changes.
     *
     * @return array{users: int[], count: int}
     */
    public function getOnlineUserIds(User $actor): array
    {
        $limit = $this->intSetting('fof-online-users-widget.max_users', self::DEFAULT_MAX_USERS, 1);
        $ttl = $this->intSetting('fof-online-users-widget.cache_ttl', self::DEFAULT_CACHE_TTL, 0);
        $interval = $this->intSetting('fof-online-users-widget.last_seen_interval', self::DEFAULT_LAST_SEEN_INTERVAL, 1);

        // The adapter returns null, without running the callback, when it cannot
        // cache (storage/cache not writable). That is a cache miss, not "nobody
        // is online".
        return $this->cache->remember(
            $this->cacheKey($actor, $limit, $ttl, $interval),
            $ttl,
            fn () => $this->queryOnlineUserIds($actor, $limit, $interval)
        ) ?? $this->queryOnlineUserIds($actor, $limit, $interval);
    }

    /**
     * @return array{users: int[], count: int}
     */
    protected function queryOnlineUserIds(User $actor, int $limit, int $interval): array
    {
        $query = User::query()
            ->whereVisibleTo($actor)
            ->where('last_seen_at', '>', Carbon::now()->subMinutes($interval));

        // user.viewLastSeenAt is a permission that allows viewing online state
        // regardless of the privacy preference not to be shown. Applied in SQL
        // so that the count and the list agree — counting before filtering
        // would let a viewer infer how many users had opted out.
        if (! $actor->hasPermission('user.viewLastSeenAt')) {
            $this->whereDisclosesOnline($query);
        }

        $count = $query->count();

        return [
            // Without an order the cap picks an arbitrary N users, and which ones
            // can change from one request to the next.
            'users' => $query->orderByDesc('last_seen_at')->orderBy('id')->limit($limit)->pluck('id')->all(),
            'count' => $count,
        ];
    }

    /**
     * Restrict to users who disclose their online status.
     *
     * `discloseOnline` defaults to true and is only persisted once the user
     * changes it, so "disclosed" covers a NULL preferences column, a
     * preferences object without the key, and the key set to true. Only an
     * explicit false opts out.
     *
     * Expressed with Laravel's JSON operators rather than raw SQL so it holds
     * on all four drivers core supports — `preferences` is a native JSON
     * column, so `->` translates per-grammar.
     */
    protected function whereDisclosesOnline(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query
                ->whereNull('preferences')
                ->orWhereNull('preferences->discloseOnline')
                ->orWhere('preferences->discloseOnline', true);
        });
    }

    /**
     * @return array{users: User[], count: int}
     */
    public function getOnlineUsers(User $actor): array
    {
        $online = $this->getOnlineUserIds($actor);

        if (empty($online['users'])) {
            return ['users' => [], 'count' => $online['count']];
        }

        // Re-checked against the visibility scope rather than trusting the
        // cached ids, so a visibility change takes effect immediately instead of
        // after cache_ttl.
        $visible = User::query()
            ->whereVisibleTo($actor)
            ->whereIn('id', $online['users'])
            ->get()
            ->keyBy('id');

        $users = [];

        // `whereIn` returns rows in whatever order the database likes; restore
        // the order the ids were ranked in.
        foreach ($online['users'] as $id) {
            if ($user = $visible->get($id)) {
                $users[] = $user;
            }
        }

        return ['users' => $users, 'count' => $online['count']];
    }
}
