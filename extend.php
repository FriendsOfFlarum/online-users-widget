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

/**
 * Read the presence roster the client passed alongside the request, if any.
 *
 * The client sends the membership of realtime's `presence-online` channel so
 * that "online" can mean "holds a websocket right now" rather than "made an
 * HTTP request in the last few minutes". The ids are a *filter*, never a
 * source of truth: everything they resolve to still passes the visibility
 * scope, the discloseOnline preference and the max_users cap server-side, so a
 * forged roster can only ever narrow what the actor was already allowed to see.
 *
 * Absent param → fall back to the last_seen_at window (guests, installs
 * without flarum/realtime, and the first render before the socket connects).
 *
 * @return int[]|null
 */
$presenceIds = function (Context $context): ?array {
    $param = $context->queryParam('onlineIds');

    if ($param === null) {
        return null;
    }

    $ids = array_filter(
        array_map('intval', explode(',', (string) $param)),
        fn (int $id) => $id > 0
    );

    // An explicitly empty roster means "nobody is connected", which is
    // distinct from "no roster supplied" and must not fall back.
    return array_values(array_unique($ids));
};

/**
 * Resolve the online users once per request.
 *
 * `totalOnlineUsers` and the `onlineUsers` relationship both need the same
 * result, and each field callback would otherwise repeat the queries.
 *
 * Memoized in a local rather than on the Context: the serializer hands each
 * field its own Context (`withField()` clones), so `setParam()` writes would
 * not be visible to the sibling field. Keyed by actor id and roster so that
 * nothing leaks between actors should the closure outlive one request.
 */
$memo = [];

$resolve = function (Context $context) use ($presenceIds, &$memo): array {
    $ids = $presenceIds($context);
    $key = $context->getActor()->id.'|'.($ids === null ? 'last-seen' : implode(',', $ids));

    return $memo[$key] ??= resolve(UserRepository::class)->getOnlineUsers($context->getActor(), $ids);
};

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canViewOnlineUsersWidget')
                ->get(fn ($model, Context $context) => $context->getActor()->hasPermission('viewOnlineUsersWidget')),

            Schema\Integer::make('totalOnlineUsers')
                ->get(fn ($model, Context $context) => $resolve($context)['count']),

            Schema\Relationship\ToMany::make('onlineUsers')
                ->type('users')
                ->includable()
                ->get(fn ($model, Context $context) => $resolve($context)['users']),
        ])
        ->endpoint(Endpoint\Show::class, fn (Endpoint\Show $endpoint) => $endpoint->addDefaultInclude(['onlineUsers'])),

    // Without this guard any logged-in user could subscribe to `presence-online`
    // and enumerate who is connected, regardless of the permission the widget
    // itself enforces. Realtime's own docblock for this hook uses this very
    // permission as its worked example.
    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-realtime', fn () => [
            (new \Flarum\Realtime\Extend\Realtime())
                ->authorizePresenceChannel(
                    'online',
                    fn (User $actor) => $actor->hasPermission('viewOnlineUsersWidget')
                ),
        ]),

    (new Extend\Settings())
        ->default('fof-online-users-widget.max_users', 15)
        ->default('fof-online-users-widget.cache_ttl', 30)
        ->default('fof-online-users-widget.last_seen_interval', 5),
];
