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
     * @param int[]|null $presenceIds
     */
    protected function cacheKey(User $actor, ?array $presenceIds = null): string
    {
        $params = [
            $actor->hasPermission('user.viewLastSeenAt') ? 'high-access' : 'low-access',
            $this->settings->get('fof-online-users-widget.max_users'),
            $this->settings->get('fof-online-users-widget.cache_ttl'),
            $this->settings->get('fof-online-users-widget.last_seen_interval'),
            // A presence-scoped result is only valid for that exact roster.
            $presenceIds === null ? 'last-seen' : 'presence:'.implode(',', $presenceIds),
        ];

        foreach (static::$cacheKeyParameters as $parameter) {
            $params[] = $parameter($actor);
        }

        return 'fof-online-users-widget.users-'.md5(implode('-', $params));
    }

    /**
     * Resolve the set of users to show as online, plus the total before the
     * `max_users` cap is applied.
     *
     * Two definitions of "online" are possible, and the caller chooses by
     * supplying (or omitting) a presence roster:
     *
     * - **Presence roster given** — the exact set of users holding a websocket
     *   right now, as reported by realtime's `presence-online` channel. This is
     *   the accurate definition: a user leaves the list the moment their socket
     *   closes, and an idle user with a live socket stays listed.
     * - **No roster** — fall back to `last_seen_at` within `last_seen_interval`.
     *   Used for guests (who are refused presence auth), for installs without
     *   flarum/realtime, and for the very first page render before the socket
     *   has connected.
     *
     * Either way the visibility scope, the `discloseOnline` preference and the
     * `max_users` cap are applied here, server-side — none of which a client
     * holding a presence roster can do for itself.
     *
     * @param int[]|null $presenceIds
     *
     * @return array{users: int[], count: int}
     */
    public function getOnlineUserIds(User $actor, ?array $presenceIds = null): array
    {
        $limit = (int) $this->settings->get('fof-online-users-widget.max_users');
        $ttl = (int) $this->settings->get('fof-online-users-widget.cache_ttl');
        $interval = (int) $this->settings->get('fof-online-users-widget.last_seen_interval');

        $result = $this->cache->remember(
            $this->cacheKey($actor, $presenceIds),
            $ttl,
            function () use ($actor, $limit, $interval, $presenceIds) {
                $query = User::query()
                    ->select('id', 'preferences')
                    ->whereVisibleTo($actor);

                if ($presenceIds === null) {
                    $query->where('last_seen_at', '>', Carbon::now()->subMinutes($interval));
                } else {
                    $query->whereIn('id', $presenceIds);
                }

                // user.viewLastSeenAt is a permission that allows viewing online state
                // regardless of the privacy preference not to be shown. Applied in SQL
                // so that the count and the list agree — counting before filtering
                // would let a viewer infer how many users had opted out.
                if (! $actor->hasPermission('user.viewLastSeenAt')) {
                    $this->whereDisclosesOnline($query);
                }

                $count = $query->count();

                return [
                    'users' => $query->limit($limit)->pluck('id')->all(),
                    'count' => $count,
                ];
            }
        );

        return $result ?: ['users' => [], 'count' => 0];
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
     * @param int[]|null $presenceIds
     *
     * @return array{users: User[], count: int}
     */
    public function getOnlineUsers(User $actor, ?array $presenceIds = null): array
    {
        $online = $this->getOnlineUserIds($actor, $presenceIds);

        if (empty($online['users'])) {
            return ['users' => [], 'count' => $online['count']];
        }

        return [
            // Re-checked against the visibility scope rather than trusting the
            // cached ids, so a visibility change takes effect immediately
            // instead of after cache_ttl.
            'users' => User::query()
                ->whereVisibleTo($actor)
                ->whereIn('id', $online['users'])
                ->get()
                ->all(),
            'count' => $online['count'],
        ];
    }
}
