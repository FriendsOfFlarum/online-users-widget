import { jest } from '@jest/globals';
import Widgets from '../stubs/forum-widgets-core/common/extend/Widgets';
import OnlineUsersWidget from '../../src/common/components/OnlineUsersWidget';
import registerWidget from '../../src/common/registerWidget';

/**
 * What the extension tells forum-widgets-core about its widget: where it goes, and
 * when it is switched off.
 */
describe('registerWidget', () => {
  let canView: boolean;

  /** Only the part of the app the registration reads. */
  const fakeApp = () => ({ forum: { attribute: jest.fn((name: string) => (name === 'canViewOnlineUsersWidget' ? canView : undefined)) } }) as any;

  const registered = () => Widgets.registered[0];

  beforeEach(() => {
    Widgets.reset();
    canView = true;
  });

  it('registers the widget once, as a unique one at the end of the sidebar', () => {
    registerWidget(fakeApp());

    expect(Widgets.registered).toHaveLength(1);
    expect(registered()).toMatchObject({ key: 'onlineUsers', component: OnlineUsersWidget, isUnique: true, placement: 'end', position: 1 });
  });

  it('extends the app under the extension id', () => {
    const app = fakeApp();

    registerWidget(app);

    expect(Widgets.extended).toEqual([{ app, extension: 'fof-online-users-widget' }]);
  });

  describe('isDisabled', () => {
    it('is false for someone who may view the widget', () => {
      registerWidget(fakeApp());

      canView = true;

      expect(registered().isDisabled()).toBe(false);
    });

    it('is true for someone who may not', () => {
      registerWidget(fakeApp());

      canView = false;

      expect(registered().isDisabled()).toBe(true);
    });

    it('depends on the permission alone, not on whether anyone is currently online', () => {
      // Keying it on the list being non-empty would unmount the widget whenever nobody
      // was visible, taking the "no users online" message down with it.
      const app = fakeApp();

      registerWidget(app);

      registered().isDisabled();

      expect(app.forum.attribute).toHaveBeenCalledTimes(1);
      expect(app.forum.attribute).toHaveBeenCalledWith('canViewOnlineUsersWidget');
    });
  });
});
