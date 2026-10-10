<?php

/*
 * This file is part of fof/online-users-widget.
 *
 * Copyright (c) 2021 Friends of Flarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\OnlineUsers\Tests\Support;

use Closure;
use FoF\ForumWidgets\SafeCacheRepositoryAdapter;

/**
 * In-memory stand-in for the widgets-core cache adapter.
 *
 * The real adapter refuses to cache at all when `storage/cache` is not writable,
 * which would make every cache assertion pass vacuously, so tests substitute
 * this one. With `$writable = false` it behaves the way the real adapter does
 * in that situation: `remember()` returns null without running the callback.
 */
class FakeCacheAdapter extends SafeCacheRepositoryAdapter
{
    /** @var array<string, mixed> */
    public array $store = [];

    public int $misses = 0;

    public function __construct(protected bool $writable = true)
    {
    }

    public function remember($key, $ttl, Closure $callback)
    {
        if (! $this->writable) {
            return null;
        }

        if (! array_key_exists($key, $this->store)) {
            $this->misses++;
            $this->store[$key] = $callback();
        }

        return $this->store[$key];
    }
}
