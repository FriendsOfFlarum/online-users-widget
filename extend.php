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

use Flarum\Api\Serializer as FlarumSerializer;
use Flarum\Api\Controller\ShowForumController;
use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ApiSerializer(FlarumSerializer\ForumSerializer::class))
        ->attribute('canViewOnlineUsersWidget', function ($serializer) {
            return $serializer->getActor()->hasPermission('viewOnlineUsersWidget');
        })
        ->attribute('totalOnlineUsers', function (FlarumSerializer\ForumSerializer $serializer) {
            return resolve(UserRepository::class)->getOnlineUsers($serializer->getActor())['count'] ?? 0;
        })
        ->hasMany('onlineUsers', FlarumSerializer\UserSerializer::class),

    (new Extend\ApiController(ShowForumController::class))
        ->addInclude(['onlineUsers'])
        ->prepareDataForSerialization(LoadForumOnlineUsersRelationship::class),

    (new Extend\Settings)
        ->default('fof-online-users-widget.max_users', 15)
        ->default('fof-online-users-widget.cache_ttl', 30)
        ->default('fof-online-users-widget.last_seen_interval', 5),
];
