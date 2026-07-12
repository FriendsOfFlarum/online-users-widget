import app from 'flarum/common/app';
import type Mithril from 'mithril';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Tooltip from 'flarum/common/components/Tooltip';
import avatar from 'flarum/common/helpers/avatar';
import Link from 'flarum/common/components/Link';
import extractText from 'flarum/common/utils/extractText';
import type User from 'flarum/common/models/User';

import Widget, { type WidgetAttrs } from 'flarum/extensions/fof-forum-widgets-core/common/components/Widget';

export default class OnlineUsersWidget extends Widget<WidgetAttrs> {

  oncreate(vnode): void {
    this.attrs.state.users = app.forum.onlineUsers() || [];
    this.attrs.state.total = app.forum.totalOnlineUsers() || 0;
    if (!this.attrs.state.timerAdded) {
    console.log("onliner timer added");
        setInterval(this.load.bind(this), 120000);
        this.attrs.state.timerAdded = true;
    }
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
    if (this.attrs.state.isLoading) {
      return <LoadingIndicator />;
    }

    this.attrs.state.users = (this.attrs.state.users || app.forum.onlineUsers()) || [];
    const users = this.attrs.state.users;
    this.attrs.state.total = (this.attrs.state.total || app.forum.totalOnlineUsers()) || 0;
    const total = this.attrs.state.total;

    return (
      <div className="FoF-OnlineUsersWidget-users">
        <div className="FoF-OnlineUsersWidget-users-message">
          {users.length === 0 ? app.translator.trans('fof-online-users-widget.forum.widget.empty') : null}
        </div>
        <div className="FoF-OnlineUsersWidget-users-list">
          {users.map((user: User) => (
            <Link href={app.route('user', { username: user.slug() })} className="FoF-OnlineUsersWidget-users-item">
              <Tooltip text={user.displayName()}>{avatar(user)}</Tooltip>
            </Link>
          ))}
          {total > users.length ? (
            <span className="FoF-OnlineUsersWidget-users-item FoF-OnlineUsersWidget-users-item--plus">
              <span className="Avatar">{total < 50? `+${total - users.length}` : `+${50 - users.length}...`}</span>
            </span>
          ) : null}
        </div>
      </div>
    );
  }

  async load(): Promise<void> {
    if (this.loadWithInitialResponse) {
      this.setResults(app.forum.onlineUsers());
      return;
    }

    this.attrs.state.isLoading = true;

    try {
      const response = await app.request<OnlineUsersResponse>({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/online-users'
      });

      app.store.pushPayload(response);

      const users = response.data.map(userData => 
        app.store.getById('users', userData.id)
      );

      this.setResults(users, response.meta);
    } catch (error) {
      console.error(error);
    } finally {
      this.attrs.state.isLoading = false;
    }
  }

  setResults(data, meta) {
    this.attrs.state.users = data;
    this.attrs.state.total = meta?.totalCount;
    this.attrs.state.isLoading = false;
    this.attrs.state.hasLoaded = true;
    m.redraw();
    console.log("redrew");
  }

}
