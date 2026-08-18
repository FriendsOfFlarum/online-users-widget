<?php

/*
 * This file is part of fof/online-users-widget.
 *
 * Copyright (c) 2021 Friends of Flarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $schema->getConnection()
            ->table('settings')
            ->where('key', 'afrux-online-users-widget.max_users')
            ->update(['key' => 'fof-online-users-widget.max_users']);
    },

    'down' => function (Builder $schema) {
        $schema->getConnection()
            ->table('settings')
            ->where('key', 'fof-online-users-widget.max_users')
            ->update(['key' => 'afrux-online-users-widget.max_users']);
    },
];
