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

/**
 * Renames the Afrux setting key to ours, and back.
 *
 * `settings.key` is the primary key, so a rename onto a key that already exists
 * throws and aborts `migrate` (and enabling the extension). That happens when
 * both rows exist — settings synced from another environment, or `down` and `up`
 * run again after the value was changed. The key already in place wins and the
 * other row is left untouched, rather than overwriting or deleting a value.
 */
$rename = function (Builder $schema, string $from, string $to): void {
    $settings = $schema->getConnection()->table('settings');

    if ($settings->clone()->where('key', $to)->exists()) {
        return;
    }

    $settings->where('key', $from)->update(['key' => $to]);
};

return [
    'up' => function (Builder $schema) use ($rename) {
        $rename($schema, 'afrux-online-users-widget.max_users', 'fof-online-users-widget.max_users');
    },

    'down' => function (Builder $schema) use ($rename) {
        $rename($schema, 'fof-online-users-widget.max_users', 'afrux-online-users-widget.max_users');
    },
];
