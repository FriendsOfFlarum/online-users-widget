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

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\User\User;
use WeakMap;

/**
 * Resolve the online users once per request.
 *
 * `totalOnlineUsers` and the `onlineUsers` relationship both need the same
 * result, and each field callback would otherwise repeat the queries.
 *
 * Memoized in a local rather than on the Context: the serializer hands each
 * field its own Context (`withField()` clones), so `setParam()` writes would
 * not be visible to the sibling field. Keyed by the request object, which every
 * cloned Context shares, so the entry cannot outlive the request in a
 * long-running worker and is released with it.
 */
$memo = new WeakMap();

$resolve = function (Context $context) use ($memo): array {
    $actor = $context->getActor();

    // The fields below are not visible without this permission, so this is
    // belt and braces: no query should ever run for an actor who cannot see it.
    if (! Access::canView($actor)) {
        return ['users' => [], 'count' => 0];
    }

    // Sent by the client after a realtime membership event, when the cached
    // answer is likely to be out of date. See UserRepository::FRESH_WINDOW.
    $fresh = (bool) $context->queryParam('onlineUsersFresh');

    return $memo[$context->request] ??= resolve(UserRepository::class)->getOnlineUsers($actor, $fresh);
};

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canViewOnlineUsersWidget')
                ->get(fn ($model, Context $context) => Access::canView($context->getActor())),

            // Whether the client should join `presence-online`. Decided here, by
            // the same rule the channel guard below applies, so the two cannot
            // disagree.
            Schema\Boolean::make('canJoinOnlineUsersPresence')
                ->get(fn ($model, Context $context) => Access::canJoinPresence($context->getActor())),

            // Omitted, not zeroed, for actors without the permission: `onlineUsers`
            // is a default include, so these reach every actor's `GET /api` and
            // the page payload unless the API itself withholds them.
            Schema\Integer::make('totalOnlineUsers')
                ->visible(fn ($model, Context $context) => Access::canView($context->getActor()))
                ->get(fn ($model, Context $context) => $resolve($context)['count']),

            Schema\Relationship\ToMany::make('onlineUsers')
                ->type('users')
                ->includable()
                ->visible(fn ($model, Context $context) => Access::canView($context->getActor()))
                ->get(fn ($model, Context $context) => $resolve($context)['users']),
        ])
        ->endpoint(Endpoint\Show::class, fn (Endpoint\Show $endpoint) => $endpoint->addDefaultInclude(['onlineUsers'])),

    // Without this guard any logged-in user could subscribe to `presence-online`
    // and enumerate who is connected, regardless of the permission the widget
    // itself enforces. Realtime's own docblock for this hook uses this very
    // permission as its worked example. Joining also announces the subscriber
    // to the others, so it respects `discloseOnline` — see Access.
    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-realtime', fn () => [
            (new \Flarum\Realtime\Extend\Realtime())
                ->authorizePresenceChannel(
                    'online',
                    fn (User $actor) => Access::canJoinPresence($actor)
                ),
        ]),

    (new Extend\Settings())
        ->default('fof-online-users-widget.max_users', UserRepository::DEFAULT_MAX_USERS)
        ->default('fof-online-users-widget.cache_ttl', UserRepository::DEFAULT_CACHE_TTL)
        ->default('fof-online-users-widget.last_seen_interval', UserRepository::DEFAULT_LAST_SEEN_INTERVAL),
];
