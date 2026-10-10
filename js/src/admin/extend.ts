import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'fof-online-users-widget.max_users',
      label: app.translator.trans('fof-online-users-widget.admin.settings.max_users'),
      type: 'number',
      min: 1,
    }))
    .setting(() => ({
      setting: 'fof-online-users-widget.last_seen_interval',
      label: app.translator.trans('fof-online-users-widget.admin.settings.last_seen_interval'),
      type: 'number',
      min: 1,
    }))
    .setting(() => ({
      setting: 'fof-online-users-widget.cache_ttl',
      label: app.translator.trans('fof-online-users-widget.admin.settings.cache_ttl'),
      type: 'number',
      min: 0,
    }))
    .permission(
      () => ({
        icon: 'fas fa-user-clock',
        label: app.translator.trans('fof-online-users-widget.admin.permissions.view_online_users_widget'),
        permission: 'viewOnlineUsersWidget',
        allowGuest: true,
      }),
      'view'
    ),
];
