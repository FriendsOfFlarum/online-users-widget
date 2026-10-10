import { jest } from '@jest/globals';
import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import { makeUser } from '@flarum/jest-config/factory';
import flatten from 'flat';
import jsYaml from 'js-yaml';
import fs from 'fs';
import path from 'path';
import mq from 'mithril-query';
import app from 'flarum/forum/app';
import OnlineUsersWidget from '../../../src/common/components/OnlineUsersWidget';
import onlineUsersState from '../../../src/common/onlineUsersState';
import forumExtenders from '../../../src/forum/extend';

beforeAll(() => bootstrapForum());

/**
 * The widget is a function of what the server last said: `app.forum` carries the users
 * and the total, and the widget shows them. Everything about keeping that current is
 * `onlineUsersState`'s job, so it is spied on here rather than run.
 */
describe('OnlineUsersWidget', () => {
  let widgetCreated: any;
  let widgetRemoved: any;
  let hydrate: any;

  /** What the server sent: these users, and this total. `onlineUsers` is the relationship. */
  const serve = (users: { id: string; username: string; attributes?: Record<string, unknown> }[], total: number) => {
    users.forEach(({ id, username, attributes }) =>
      app.store.pushObject(
        makeUser({ id, attributes: { username, displayName: username, slug: username, joinTime: '2020-05-21T11:51:06+00:00', ...attributes } })
      )
    );

    app.forum.pushData({
      attributes: { totalOnlineUsers: total },
      relationships: { onlineUsers: { data: users.map(({ id }) => ({ type: 'users', id })) } },
    } as any);
  };

  const mount = () => mq(OnlineUsersWidget as any, { state: {} });

  /** An attribute of an element, as mithril-query's fake DOM keeps it (what `toHaveElementAttr` reads). */
  const attr = (node: any, name: string): string | undefined => node[name] ?? node._attrsByQName?.[name]?.data;

  beforeAll(() => {
    app.boot();

    // The model attributes the extension declares for the forum.
    forumExtenders.forEach((extender: any) => extender.extend(app, { name: 'fof-online-users-widget' }));

    // The extension's own translations, so what is asserted is what the user would read,
    // and a key the component uses but the locale lacks would show up here.
    const locale = jsYaml.load(fs.readFileSync(path.resolve(process.cwd(), '../locale/en.yml'), 'utf8')) as object;
    app.translator.addTranslations(flatten(locale));

    app.routes.user = { path: '/u/:username', component: {} } as any;
  });

  beforeEach(() => {
    widgetCreated = jest.spyOn(onlineUsersState, 'widgetCreated').mockImplementation(() => {});
    widgetRemoved = jest.spyOn(onlineUsersState, 'widgetRemoved').mockImplementation(() => {});
    hydrate = jest.spyOn(onlineUsersState, 'hydrate').mockImplementation(() => {});

    serve([], 0);
  });

  afterEach(() => jest.restoreAllMocks());

  describe('the list', () => {
    it('shows each online user, linked to their profile', () => {
      serve(
        [
          { id: '10', username: 'alice' },
          { id: '11', username: 'bob' },
        ],
        2
      );

      const widget = mount();

      expect(widget).toHaveElement('.FoF-OnlineUsersWidget-users-item');
      expect(widget.find('a.FoF-OnlineUsersWidget-users-item')).toHaveLength(2);
      expect(attr(widget.find('a.FoF-OnlineUsersWidget-users-item')[0], 'href')).toContain('alice');
      expect(attr(widget.find('a.FoF-OnlineUsersWidget-users-item')[1], 'href')).toContain('bob');
      expect(widget).not.toContainRaw('No Users Currently Online.');
    });

    it('says so when nobody is online', () => {
      const widget = mount();

      expect(widget).not.toHaveElement('a.FoF-OnlineUsersWidget-users-item');
      expect(widget).toContainRaw('No Users Currently Online.');
    });

    it('copes with the server sending no list at all', () => {
      // `onlineUsers()` is `false` when the relationship is absent, as it is for someone
      // without the permission.
      app.forum.data.relationships = {};

      expect(() => mount()).not.toThrow();
      expect(mount()).toContainRaw('No Users Currently Online.');
    });

    it('skips a user the store does not hold, rather than failing', () => {
      serve([{ id: '10', username: 'alice' }], 2);
      app.forum.pushData({
        relationships: {
          onlineUsers: {
            data: [
              { type: 'users', id: '10' },
              { type: 'users', id: '999' }, // never pushed
            ],
          },
        },
      } as any);

      expect(mount().find('a.FoF-OnlineUsersWidget-users-item')).toHaveLength(1);
    });
  });

  describe('the "more" chip', () => {
    const chip = (widget: any) => widget.find('.FoF-OnlineUsersWidget-users-item--plus');

    it('counts the online users that are not listed, because of the cap', () => {
      serve([{ id: '10', username: 'alice' }], 5);

      const widget = mount();

      expect(chip(widget)).toHaveLength(1);
      expect(widget).toContainRaw('+4');
    });

    it('is labelled for a screen reader, since "+4" alone says nothing', () => {
      serve([{ id: '10', username: 'alice' }], 5);

      const [element] = chip(mount());

      expect(attr(element, 'role')).toBe('img');
      expect(attr(element, 'aria-label')).toBe('4 more users online');
      expect(attr(element, 'title')).toBe('4 more users online');
    });

    it('is singular for one more', () => {
      serve([{ id: '10', username: 'alice' }], 2);

      expect(attr(chip(mount())[0], 'aria-label')).toBe('1 more user online');
    });

    it('is not shown when everyone online is listed', () => {
      serve([{ id: '10', username: 'alice' }], 1);

      expect(chip(mount())).toHaveLength(0);
    });

    it('is not shown when the total is missing', () => {
      serve([{ id: '10', username: 'alice' }], 0);
      app.forum.data.attributes.totalOnlineUsers = undefined;

      expect(chip(mount())).toHaveLength(0);
    });
  });

  describe('avatars', () => {
    const SRCSET = 'https://forum.test/avatars/a.webp 1x, https://forum.test/avatars/a@2x.webp 2x, https://forum.test/avatars/a@3x.webp 3x';

    it('offers the browser the HiDPI variants the server lists, so avatars stay sharp on dense screens', () => {
      serve([{ id: '10', username: 'alice', attributes: { avatarUrl: 'https://forum.test/avatars/a.webp', avatarSrcset: SRCSET } }], 1);

      const widget = mount();

      expect(attr(widget.first('img'), 'src')).toBe('https://forum.test/avatars/a.webp');
      expect(attr(widget.first('img'), 'srcset')).toBe(SRCSET);
    });

    it('does not make up a srcset for an avatar the server has no variants for', () => {
      serve([{ id: '10', username: 'alice', attributes: { avatarUrl: 'https://forum.test/avatars/a.webp', avatarSrcset: null } }], 1);

      const widget = mount();

      expect(widget).toHaveElement('img');
      // Unset reads as empty in mithril-query's fake DOM.
      expect(attr(widget.first('img'), 'srcset')).toBeFalsy();
    });

    it('shows the initial of someone with no avatar, rather than an image', () => {
      // The test factory gives every user an avatar unless told otherwise.
      serve([{ id: '10', username: 'alice', attributes: { avatarUrl: null, avatarSrcset: null } }], 1);

      const widget = mount();

      expect(widget).not.toHaveElement('img');
      expect(widget).toContainRaw('A');
    });
  });

  describe('telling the state someone is looking', () => {
    it('announces itself when it mounts', () => {
      mount();

      expect(widgetCreated).toHaveBeenCalledTimes(1);
    });

    it('announces when it goes away', () => {
      const widget = mount();

      expect(widgetRemoved).not.toHaveBeenCalled();

      widget.onremove();

      expect(widgetRemoved).toHaveBeenCalledTimes(1);
    });
  });

  describe('clicking a user', () => {
    it('has that user fetched in full, because the profile it opens is rendered from the store', () => {
      serve(
        [
          { id: '10', username: 'alice' },
          { id: '11', username: 'bob' },
        ],
        2
      );

      mount().click('a.FoF-OnlineUsersWidget-users-item');

      expect(hydrate).toHaveBeenCalledTimes(1);
      expect(hydrate.mock.calls[0][0].username()).toBe('alice');
    });
  });
});
