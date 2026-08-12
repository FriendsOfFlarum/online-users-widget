<?php

/*
 * This file is part of fof/online-users-widget.
 *
 * Copyright (c) 2021 Friends of Flarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\ConnectionInterface;

/**
 * Carry the setting over from the Afrux-era key.
 *
 * This extension replaces afrux/online-users-widget, and defaults now come from
 * `Extend\Settings` in extend.php rather than being written here. Anyone
 * upgrading from Afrux still has their configured value under the old key, so
 * copy it across; without this they silently fall back to the default of 15.
 */
return [
    'up' => function (ConnectionInterface $db) {
        $old = $db->table('settings')
            ->where('key', 'afrux-online-users-widget.max_users')
            ->value('value');

        if ($old === null) {
            return;
        }

        $exists = $db->table('settings')
            ->where('key', 'fof-online-users-widget.max_users')
            ->exists();

        if ($exists) {
            return;
        }

        $db->table('settings')->insert([
            'key'   => 'fof-online-users-widget.max_users',
            'value' => $old,
        ]);
    },

    // Intentionally irreversible: the Afrux key is left in place by `up`, so
    // there is nothing to restore and removing the new key would discard a
    // value the user may since have changed.
    'down' => function (ConnectionInterface $db) {
    },
];
