# Online Users Widget

![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/online-users-widget.svg?style=flat-square)](https://packagist.org/packages/fof/online-users-widget) [![Total Downloads](https://img.shields.io/packagist/dt/fof/online-users-widget.svg?style=flat-square)](https://packagist.org/packages/fof/online-users-widget)

A [Flarum](http://flarum.org) extension. Forum Widget That Lists Online Users.

![flarum lan_ (3)](https://user-images.githubusercontent.com/20267363/127892981-e536c329-17bd-4ecf-ae5d-f295e26f8d1b.png)

> [!NOTE]
> This package was previously maintained as [`afrux/online-users-widget`](https://github.com/afrux/online-users-widget) by [@SychO9](https://github.com/SychO9). It has been transferred to FriendsOfFlarum and is now published as `fof/online-users-widget`. The `composer.json` `replace` field ensures existing installs upgrade transparently — no manual removal needed.

## Installation

This will also install [Forum Widgets Core](https://github.com/FriendsOfFlarum/forum-widgets-core) as it relies on it.

Install with composer:

```sh
composer require fof/online-users-widget:"*"
```

### Migrating from `afrux/online-users-widget`

If you currently have `afrux/online-users-widget` installed, run:

```sh
composer require fof/online-users-widget:"*"
composer remove afrux/online-users-widget
php flarum cache:clear
```

## Updating

```sh
composer update fof/online-users-widget:"*" --with-dependencies
php flarum migrate
php flarum cache:clear
```

## flarum/realtime integration

If [flarum/realtime](https://github.com/flarum/framework/tree/2.x/extensions/realtime) is installed, the widget automatically switches to live updates via the `presence-online` WebSocket presence channel. No configuration is required.

When realtime is active:

- The widget updates **instantly** when users connect or disconnect, rather than reflecting a cached snapshot from page load.
- All logged-in users are tracked via the presence channel, so the list stays accurate as people arrive and leave.
- Users without the `viewOnlineUsersWidget` permission still join the presence channel (so they are visible to others) but the widget is not activated for them.

When realtime is not installed the widget falls back to the standard behaviour: the online users list is populated from the forum API payload on page load, cached server-side according to the configured TTL.

> [!NOTE]
> The presence channel tracks **active WebSocket connections**, not `last_seen_at`. A user appears in the widget as soon as their browser connects and disappears as soon as their last tab closes, regardless of the configured "last seen interval" setting. The last seen interval setting only affects the fallback (non-realtime) behaviour.
>
> A user with multiple tabs or browsers open counts as a single online user. They appear in the widget on their first connection and are removed only when all their connections close.

## Links

- [Packagist](https://packagist.org/packages/fof/online-users-widget)
- [GitHub](https://github.com/FriendsOfFlarum/online-users-widget)
- [Discuss](https://discuss.flarum.org/d/39065)
