import app from 'flarum/common/app';
import type Mithril from 'mithril';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Tooltip from 'flarum/common/components/Tooltip';
import Avatar from 'flarum/common/components/Avatar';
import Link from 'flarum/common/components/Link';
import extractText from 'flarum/common/utils/extractText';
import type User from 'flarum/common/models/User';

import Widget, { type WidgetAttrs } from 'ext:fof/forum-widgets-core/common/components/Widget';
import onlineUsersState from '../onlineUsersState';

export default class OnlineUsersWidget extends Widget<WidgetAttrs> {
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
    // Always the server's answer. Realtime keeps it current by refetching when
    // presence membership changes, rather than substituting its own list — see
    // forum/extendRealtime.ts.
    const users: User[] = (app.forum.onlineUsers() || []).filter((u): u is User => u !== undefined);
    const total: number = app.forum.totalOnlineUsers() || 0;

    // Only the very first load has nothing to show; later refetches swap
    // results in underneath the existing list rather than blanking it.
    if (onlineUsersState.isLoading && users.length === 0) {
      return <LoadingIndicator />;
    }

    return (
      <div className="FoF-OnlineUsersWidget-users">
        <div className="FoF-OnlineUsersWidget-users-message">
          {users.length === 0 ? app.translator.trans('fof-online-users-widget.forum.widget.empty') : null}
        </div>
        <div className="FoF-OnlineUsersWidget-users-list">
          {users.map((user: User) => (
            <Link href={app.route('user', { username: user.slug() })} className="FoF-OnlineUsersWidget-users-item">
              <Tooltip text={user.displayName()}>
                <Avatar user={user} />
              </Tooltip>
            </Link>
          ))}
          {total > users.length ? (
            <span className="FoF-OnlineUsersWidget-users-item FoF-OnlineUsersWidget-users-item--plus">
              <span className="Avatar">{`+${total - users.length}`}</span>
            </span>
          ) : null}
        </div>
      </div>
    );
  }
}
