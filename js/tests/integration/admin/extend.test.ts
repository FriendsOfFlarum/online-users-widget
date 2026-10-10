import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import extenders from '../../../src/admin/extend';

beforeAll(() => bootstrapAdmin());

/**
 * What the admin sees: the three settings and the permission. The extender registers
 * them as the admin app mounts, so this drives the real path: boot the extension, then
 * run what would run before mount.
 */
describe('admin extend', () => {
  const EXTENSION = 'fof-online-users-widget';

  const setting = (key: string) => app.registry.getData()[EXTENSION].settings.get(key);

  beforeAll(() => {
    app.boot();
    app.bootExtensions({ [EXTENSION]: { extend: extenders } });
    (app as any).runBeforeMount();
  });

  it('registers the three settings as number inputs', () => {
    for (const key of ['max_users', 'last_seen_interval', 'cache_ttl']) {
      expect(setting(`fof-online-users-widget.${key}`)).toMatchObject({ type: 'number' });
    }
  });

  it('keeps each setting within what the server will accept', () => {
    // The server falls back to its defaults for blank or non-numeric values, and clamps
    // the rest; the inputs stop an admin entering what would have to be clamped.
    expect(setting('fof-online-users-widget.max_users').min).toBe(1);
    expect(setting('fof-online-users-widget.last_seen_interval').min).toBe(1);
    // Zero turns caching off, which is a legitimate choice.
    expect(setting('fof-online-users-widget.cache_ttl').min).toBe(0);
  });

  it('registers the permission among "view", and lets it be granted to guests', () => {
    const permission = app.registry.getData()[EXTENSION].permissions.view.get('viewOnlineUsersWidget');

    expect(permission).toMatchObject({ permission: 'viewOnlineUsersWidget', allowGuest: true });
  });
});
