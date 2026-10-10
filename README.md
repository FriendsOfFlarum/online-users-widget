# Online Users Widget

![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/online-users-widget.svg?style=flat-square)](https://packagist.org/packages/fof/online-users-widget) [![Total Downloads](https://img.shields.io/packagist/dt/fof/online-users-widget.svg?style=flat-square)](https://packagist.org/packages/fof/online-users-widget)

A [Flarum](http://flarum.org) extension. Forum Widget That Lists Online Users.

![flarum lan_ (3)](https://user-images.githubusercontent.com/20267363/127892981-e536c329-17bd-4ecf-ae5d-f295e26f8d1b.png)

> [!NOTE]
> This package was previously maintained as [`afrux/online-users-widget`](https://github.com/afrux/online-users-widget) by [@SychO9](https://github.com/SychO9). It has been transferred to FriendsOfFlarum and is now published as `fof/online-users-widget`. The `composer.json` `replace` field ensures existing installs upgrade transparently — no manual removal needed.

## Requirements

- Flarum 2.x
- [Forum Widgets Core](https://github.com/FriendsOfFlarum/forum-widgets-core), which is installed with this extension
- Optionally, [flarum/realtime](https://github.com/flarum/framework/tree/2.x/extensions/realtime) for [live updates](#live-updates-with-flarumrealtime)

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

## Setup

1. Enable the extension.
2. **Grant the permission.** Nobody but administrators can see the widget until you do. Under *Admin → Permissions*, in the *View* section, grant **View Online Users Widget** to the groups that should see it. It can be granted to guests too.
3. The widget is placed in the forum sidebar by default. Forum Widgets Core lets you move it; see its [README](https://github.com/FriendsOfFlarum/forum-widgets-core).

## Settings

| Setting | Default | What it does |
| --- | --- | --- |
| Max shown users | 15 | The most users listed. Anyone online beyond that is summarised as a "+N" chip. At least 1. |
| Last seen interval to consider a user online (in minutes) | 5 | Someone counts as online if they were seen within this long. At least 1. |
| Time to cache the users list (in seconds) | 30 | How long the list is kept before it is worked out again. 0 turns caching off. |

A blank or non-numeric value falls back to the default.

## Who is shown, and to whom

- **Online means recently seen.** A member counts as online if they were last seen within the last seen interval. That is the time of their last request, not whether they have a tab open.
- **Only people with the permission are sent the list.** This is enforced by the API, not just by hiding the widget: for anyone without **View Online Users Widget** the forum payload carries neither the list nor the total.
- **Members can opt out.** Someone who turns off *Allow others to see when I am online* in their privacy settings is left out of both the list and the total. Viewers who have the permission to see when users were last seen (`user.viewLastSeenAt`) still see them.
- **Everyone sees only the users they are allowed to see**, according to the forum's normal user visibility. On a forum that is not visible to guests, for example, a guest is shown nobody.
- **The "+N" chip counts the rest.** It is the total online minus the users listed, so it is also affected by the opt-out above.
- Avatars use the HiDPI variants of a member's picture when the forum has them.

The list is cached for the configured time, separately for each audience, so a viewer is never served a list worked out for someone who can see different users.

## Keeping it up to date

Without realtime, the list arrives with the page. When you navigate back to a page that shows the widget and what is on screen is more than 30 seconds old, it is fetched again, so it does not stay frozen for the length of a visit.

### Live updates with flarum/realtime

If [flarum/realtime](https://github.com/flarum/framework/tree/2.x/extensions/realtime) is installed and enabled, the widget also refreshes as people arrive. No configuration is needed.

```sh
composer require flarum/realtime
```

- **Presence is only a signal.** When someone arrives, other viewers' widgets ask the server for the list again, a few seconds later (between 2 and 5, so that everyone does not ask at once). What is shown is always the server's answer, with the same rules as above. The websocket never supplies the list itself.
- **Only arrivals trigger it.** Someone leaving does not remove them straight away; they drop off once they have not been seen for the last seen interval.
- **Nothing happens while the widget is out of sight.** A member joins the presence channel only once a page showing the widget has been opened. If something changed while they were on another page, they catch up when they come back.
- **Who joins the channel.** Joining announces the member by display name to everyone else in it, so it is restricted: they must have the **View Online Users Widget** permission, and members who have opted out of being shown as online do not join (so they get no live updates) unless they have the permission to see when users were last seen. Guests do not join and do not get live updates.

This extension does not use [flarum/pusher](https://github.com/flarum/framework/tree/2.x/extensions/pusher) and has no plans to.

## For developers

The online users are cached per audience. The cache key already accounts for whether the viewer can see the forum and whether they can see when users were last seen. If your extension changes which users a viewer can see in some other way, for example a user visibility scope that depends on the viewer's group, tell the widget what distinguishes one audience from another. Otherwise viewers with different visible users would share one cache entry.

```php
use Flarum\User\User;
use FoF\OnlineUsers\Extend\OnlineUsers;

return [
    (new OnlineUsers())
        ->cacheKeyParameters(fn (User $actor): string => $actor->groups->pluck('id')->sort()->implode('.')),
];
```

`cacheKeyParameters()` takes a callable, or the name of an invokable class resolved from the container. It receives the viewer and returns a string. Viewers who get the same string share a cache entry.

## Troubleshooting

- **The widget does not appear.** Check that **View Online Users Widget** has been granted to the viewer's group (it is not granted to anyone by default), and that Forum Widgets Core has it in a placement. Forum Widgets Core only draws widgets on the discussion list's page, so a forum whose home page is another route may not show it; see [forum-widgets-core#14](https://github.com/FriendsOfFlarum/forum-widgets-core/issues/14).
- **Nobody is listed, but people are online.** They may have opted out (see above), or this viewer may not be allowed to see them.
- **The list is a little behind.** It is cached for the configured time. Lower **Time to cache**, or install flarum/realtime.

## Development

```sh
composer install
composer test:setup       # once, to prepare the test database. It wipes the database it points at,
                          # so use a dedicated one.
composer test             # unit and integration tests
composer analyse:phpstan

cd js
yarn install
yarn test                 # frontend tests
yarn format               # before committing JS changes
```

## Links

- [Packagist](https://packagist.org/packages/fof/online-users-widget)
- [GitHub](https://github.com/FriendsOfFlarum/online-users-widget)
- [Discuss](https://discuss.flarum.org/d/39065)
