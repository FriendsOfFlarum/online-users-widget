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
 * The rename itself can't be driven through `extension()`: migrations run during
 * boot, before `prepareDatabase()` can seed the Afrux row they look for. So the
 * closures are invoked the way `Flarum\Database\Migrator` invokes them — with
 * `$connection->getSchemaBuilder()`, which is also what pins the argument type.
 */
class AfruxSettingMigrationTest extends TestCase
{
    protected const OLD_KEY = 'afrux-online-users-widget.max_users';
    protected const NEW_KEY = 'fof-online-users-widget.max_users';

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-forum-widgets-core', 'fof-online-users-widget');
    }

    protected function runMigration(string $direction): void
    {
        $migration = include __DIR__.'/../../migrations/2021_08_01_000000_set_default_settings.php';

        $migration[$direction]($this->database()->getSchemaBuilder());
    }

    protected function storedValue(string $key)
    {
        return $this->database()->table('settings')->where('key', $key)->value('value');
    }

    public function test_afrux_value_is_renamed()
    {
        $this->prepareDatabase(['settings' => [['key' => self::OLD_KEY, 'value' => '42']]]);

        $this->runMigration('up');

        $this->assertEquals('42', $this->storedValue(self::NEW_KEY));
        $this->assertNull($this->storedValue(self::OLD_KEY));
    }

    public function test_nothing_is_written_when_there_is_no_afrux_value()
    {
        $this->runMigration('up');

        $this->assertNull($this->storedValue(self::NEW_KEY));
    }

    public function test_down_restores_the_afrux_key()
    {
        $this->prepareDatabase(['settings' => [['key' => self::OLD_KEY, 'value' => '42']]]);

        $this->runMigration('up');
        $this->runMigration('down');

        $this->assertEquals('42', $this->storedValue(self::OLD_KEY));
        $this->assertNull($this->storedValue(self::NEW_KEY));
    }
}
