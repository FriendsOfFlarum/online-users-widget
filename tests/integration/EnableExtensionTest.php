<?php

/*
 * This file is part of fof/online-users-widget.
 *
 * Copyright (c) 2021 Friends of Flarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\OnlineUsers\Tests\integration;

use Flarum\Testing\integration\TestCase;

/**
 * `extension()` enables the extension at boot, which runs its migrations — so
 * any request made here fails outright if a migration cannot run.
 *
 * PHPStan cannot cover that: `migrations/` is not in its `paths`, and even if
 * it were, it has no way to know what Flarum's `Migrator` passes into a
 * closure migration.
 */
class EnableExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-forum-widgets-core', 'fof-online-users-widget');
    }

    public function test_forum_resource_exposes_the_widget_fields()
    {
        $response = $this->send($this->request('GET', '/api'));

        $this->assertEquals(200, $response->getStatusCode());

        $attributes = json_decode($response->getBody()->getContents(), true)['data']['attributes'];

        $this->assertArrayHasKey('canViewOnlineUsersWidget', $attributes);
        $this->assertArrayHasKey('totalOnlineUsers', $attributes);
    }
}
