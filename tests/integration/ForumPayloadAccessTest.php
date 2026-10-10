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

use Flarum\Group\Group;
use Flarum\User\User;

/**
 * `viewOnlineUsersWidget` has to be enforced by the API, not just by the widget
 * hiding itself: `onlineUsers` is a default include on the forum resource, so
 * anything it serializes reaches every actor's `GET /api` and the HTML shell.
 */
class ForumPayloadAccessTest extends OnlineUsersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            User::class => [
                $this->user(2),
                $this->user(3),
            ],
        ]);
    }

    protected function forumDocument(?int $actorId = null): array
    {
        $options = $actorId === null ? [] : ['authenticatedAs' => $actorId];

        $response = $this->send($this->request('GET', '/api', $options));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        return json_decode((string) $response->getBody(), true);
    }

    public function test_guest_without_the_permission_gets_no_online_data()
    {
        $document = $this->forumDocument();

        $this->assertArrayNotHasKey('totalOnlineUsers', $document['data']['attributes']);
        $this->assertArrayNotHasKey('onlineUsers', $document['data']['relationships'] ?? []);
    }

    public function test_member_without_the_permission_gets_no_online_data()
    {
        $document = $this->forumDocument(2);

        $this->assertArrayNotHasKey('totalOnlineUsers', $document['data']['attributes']);
        $this->assertArrayNotHasKey('onlineUsers', $document['data']['relationships'] ?? []);
        $this->assertFalse($document['data']['attributes']['canViewOnlineUsersWidget']);
    }

    public function test_member_with_the_permission_gets_the_count_and_users()
    {
        $this->grant('viewOnlineUsersWidget', Group::MEMBER_ID);

        $document = $this->forumDocument(2);

        $this->assertTrue($document['data']['attributes']['canViewOnlineUsersWidget']);
        $this->assertEquals(2, $document['data']['attributes']['totalOnlineUsers']);

        $ids = array_column($document['data']['relationships']['onlineUsers']['data'], 'id');
        sort($ids);

        $this->assertEquals(['2', '3'], $ids);
    }

    public function test_guest_with_the_permission_gets_the_count_and_users()
    {
        $this->grant('viewOnlineUsersWidget', Group::GUEST_ID);

        $document = $this->forumDocument();

        $this->assertTrue($document['data']['attributes']['canViewOnlineUsersWidget']);
        $this->assertEquals(2, $document['data']['attributes']['totalOnlineUsers']);
        $this->assertCount(2, $document['data']['relationships']['onlineUsers']['data']);
    }
}
