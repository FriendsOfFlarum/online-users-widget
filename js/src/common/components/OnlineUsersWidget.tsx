import app from 'flarum/common/app';
import type Mithril from 'mithril';
import Tooltip from 'flarum/common/components/Tooltip';
import Avatar from 'flarum/common/components/Avatar';
import Link from 'flarum/common/components/Link';
import extractText from 'flarum/common/utils/extractText';
import type User from 'flarum/common/models/User';

import Widget, { type WidgetAttrs } from 'ext:fof/forum-widgets-core/common/components/Widget';
import onlineUsersState from '../onlineUsersState';

export default class OnlineUsersWidget extends Widget<WidgetAttrs> {
  oncreate(vnode: Mithril.VnodeDOM<WidgetAttrs, this>): void {
    super.oncreate(vnode);

    // Mounting is what tells the state that someone is looking, so it can catch
    // up if the list went stale while they were on another page.
    onlineUsersState.widgetCreated();
  }

  onremove(vnode: Mithril.VnodeDOM<WidgetAttrs, this>): void {
    super.onremove(vnode);

    onlineUsersState.widgetRemoved();
  }

  className(): string {
    return 'FoF-OnlineUsersWidget';
  }

  icon(): string {
    return 'fas fa-user-friends';
  }

  title(): string {
    return extractText(app.translator.trans('fof-online-users-widget.forum.widget.title'));
  }

  content(): Mithril.Children {
    // Always the server's answer. onlineUsersState keeps it current by refetching
    // when it goes stale or presence says it has changed, rather than substituting
    // a list of its own — see forum/extendRealtime.ts.
    const users: User[] = (app.forum.onlineUsers() || []).filter((u): u is User => u !== undefined);
    const total: number = app.forum.totalOnlineUsers() || 0;
    const more = total - users.length;

    return (
      <div className="FoF-OnlineUsersWidget-users">
        <div className="FoF-OnlineUsersWidget-users-message">
          {users.length === 0 ? app.translator.trans('fof-online-users-widget.forum.widget.empty') : null}
        </div>
        <div className="FoF-OnlineUsersWidget-users-list">
          {users.map((user: User) => (
            <Link
              key={user.id()}
              href={app.route('user', { username: user.slug() })}
              className="FoF-OnlineUsersWidget-users-item"
              // The profile this opens is built from the store, which only holds a bare
              // record of the user. See OnlineUsersState.hydrate().
              onclick={() => onlineUsersState.hydrate(user)}
            >
              <Tooltip text={user.displayName()}>
                <Avatar user={user} />
              </Tooltip>
            </Link>
          ))}
          {more > 0 ? this.moreChip(more) : null}
        </div>
      </div>
    );
  }

  /**
   * The "+N" chip. Its text alone ("+3") means nothing to a screen reader, so the
   * label says what it counts.
   */
  protected moreChip(count: number): Mithril.Children {
    const label = extractText(app.translator.trans('fof-online-users-widget.forum.widget.more', { count }));

    return (
      <span className="FoF-OnlineUsersWidget-users-item FoF-OnlineUsersWidget-users-item--plus" role="img" aria-label={label} title={label}>
        <span className="Avatar" aria-hidden="true">{`+${count}`}</span>
      </span>
    );
  }
}
