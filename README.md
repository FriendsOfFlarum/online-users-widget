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

## Links

- [Packagist](https://packagist.org/packages/fof/online-users-widget)
- [GitHub](https://github.com/FriendsOfFlarum/online-users-widget)
- [Discuss](https://discuss.flarum.org/d/PUT_DISCUSS_SLUG_HERE)
