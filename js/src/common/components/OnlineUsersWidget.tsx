import app from 'flarum/common/app';
import type Mithril from 'mithril';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Tooltip from 'flarum/common/components/Tooltip';
import Avatar from 'flarum/common/components/Avatar';
import Link from 'flarum/common/components/Link';
import extractText from 'flarum/common/utils/extractText';
import type User from 'flarum/common/models/User';

import Widget, { type WidgetAttrs } from 'ext:fof/forum-widgets-core/common/components/Widget';

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
    if (this.attrs.state.isLoading) {
      return <LoadingIndicator />;
    }

    const users = app.forum.onlineUsers() || [];
    const total = app.forum.totalOnlineUsers() || 0;

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
