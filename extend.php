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
                ->get(fn ($model, Context $context) => resolve(UserRepository::class)->getOnlineUsers($context->getActor())['count'] ?? 0),

            Schema\Relationship\ToMany::make('onlineUsers')
                ->type('users')
                ->includable()
                ->get(fn ($model, Context $context) => resolve(UserRepository::class)->getOnlineUsers($context->getActor())['users'] ?? []),
        ])
        ->endpoint(Endpoint\Show::class, fn (Endpoint\Show $endpoint) => $endpoint->addDefaultInclude(['onlineUsers'])),

    (new Extend\Settings)
        ->default('fof-online-users-widget.max_users', 15)
        ->default('fof-online-users-widget.cache_ttl', 30)
        ->default('fof-online-users-widget.last_seen_interval', 5),
];
