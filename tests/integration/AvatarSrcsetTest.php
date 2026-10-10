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
 * Core's `Avatar` component puts a user's `avatarSrcset` on the image, which is what
 * lets a HiDPI screen load the sharper variants. The widget renders core's `Avatar`, so
 * what it needs is for the online users to arrive with that attribute.
 *
 * The srcset is built from the `has_avatar_2x` and `has_avatar_3x` columns on the user
 * (precisely so that reading it needs no filesystem checks), so it silently disappears
 * if the users are loaded with only some of their columns.
 */
class AvatarSrcsetTest extends OnlineUsersTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->grant('viewOnlineUsersWidget', Group::MEMBER_ID);

        $this->prepareDatabase([
            User::class => [
                $this->user(2, null),
                // An uploaded avatar, with both HiDPI variants.
                $this->user(10, 1, ['avatar_url' => 'abc.webp', 'has_avatar_2x' => 1, 'has_avatar_3x' => 1]),
                // An uploaded avatar with no variants (uploaded before they existed).
                $this->user(11, 1, ['avatar_url' => 'def.webp', 'has_avatar_2x' => 0, 'has_avatar_3x' => 0]),
                // No avatar at all.
                $this->user(12, 1),
            ],
        ]);
    }

    /**
     * `avatarSrcset` of each online user, keyed by user id.
     *
     * @return array<string, string|null>
     */
    protected function srcsets(array $query = []): array
    {
        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 2])->withQueryParams($query));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $document = json_decode((string) $response->getBody(), true);
        $online = array_column($document['data']['relationships']['onlineUsers']['data'], 'id');

        $srcsets = [];

        foreach ($document['included'] ?? [] as $resource) {
            if ($resource['type'] === 'users' && in_array($resource['id'], $online, true)) {
                $srcsets[$resource['id']] = $resource['attributes']['avatarSrcset'] ?? null;
            }
        }

        return $srcsets;
    }

    protected function assertHiDpi(array $srcsets): void
    {
        // 1x, 2x and 3x of the same upload, as density descriptors, which need no `sizes`.
        $this->assertMatchesRegularExpression('#abc\.webp 1x, \S*abc@2x\.webp 2x, \S*abc@3x\.webp 3x$#', (string) ($srcsets['10'] ?? null));

        $this->assertNull($srcsets['11'], 'no variants, so nothing to offer');
        $this->assertNull($srcsets['12'], 'no avatar, so nothing to offer');
    }

    public function test_the_online_users_arrive_with_their_hidpi_variants()
    {
        $this->assertHiDpi($this->srcsets());
    }

    public function test_so_do_they_when_the_list_is_refetched()
    {
        $this->assertHiDpi($this->srcsets(['include' => 'onlineUsers']));
    }
}
