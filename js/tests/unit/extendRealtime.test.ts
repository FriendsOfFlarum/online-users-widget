import { jest } from '@jest/globals';
import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import realtimeState from '../stubs/realtime/forum/RealtimeState';
import extendRealtime from '../../src/forum/extendRealtime';
import { OnlineUsersState } from '../../src/common/onlineUsersState';

beforeAll(() => bootstrapForum());

/**
 * Presence is only a signal that the list has probably changed; these tests are about
 * when the extension joins the presence channel and what it does with the events, not
 * about the list itself (see onlineUsersState.test.ts).
 */
describe('extendRealtime', () => {
  let state: OnlineUsersState;
  let presenceChanged: any;
  let subscribe: any;
  let channel: { bind: any };

  /** A channel as pusher hands one over: each (re)connect brings a fresh object. */
  const newChannel = () => ({ bind: jest.fn() });

  const memberAddedHandler = (from = channel) => from.bind.mock.calls.find(([event]: [string]) => event === 'pusher:member_added')?.[1];

  beforeAll(() => {
    app.boot();
  });

  beforeEach(() => {
    realtimeState.reset();

    channel = newChannel();
    subscribe = jest.fn(() => channel);
    (app as any).websocket = { subscribe };

    app.forum.pushAttributes({ canJoinOnlineUsersPresence: true });

    state = new OnlineUsersState();
    // Only whether it was called matters here. Letting it run would arm a real timer for a
    // refetch that these tests do not look at.
    presenceChanged = jest.spyOn(state, 'presenceChanged').mockImplementation(() => {});

    // Nothing needs refetching in these tests; keep requests out of them.
    jest.spyOn(app, 'request').mockImplementation((() => new Promise(() => {})) as any);
    jest.spyOn(app.store, 'pushPayload').mockImplementation((() => ({})) as any);

    extendRealtime(state);
  });

  afterEach(() => {
    jest.restoreAllMocks();
    delete (app as any).websocket;
  });

  describe('joining the presence channel', () => {
    it('does not, however ready the channels are, until a widget is on screen', () => {
      realtimeState.notifyUserChannelReady({});
      realtimeState.notifyPublicChannelReady({});

      expect(subscribe).not.toHaveBeenCalled();
    });

    it('happens when the first widget mounts, if the connection is up', () => {
      state.widgetCreated();

      expect(subscribe).toHaveBeenCalledTimes(1);
      expect(subscribe).toHaveBeenCalledWith('presence-online');
    });

    it('waits for the connection when a widget mounted before it was up', () => {
      delete (app as any).websocket;

      state.widgetCreated();
      expect(subscribe).not.toHaveBeenCalled();

      (app as any).websocket = { subscribe };
      realtimeState.notifyUserChannelReady({});

      expect(subscribe).toHaveBeenCalledTimes(1);
    });

    it('also follows the public channel, which is what a connection without a user announces', () => {
      delete (app as any).websocket;
      state.widgetCreated();

      (app as any).websocket = { subscribe };
      realtimeState.notifyPublicChannelReady({});

      expect(subscribe).toHaveBeenCalledTimes(1);
    });

    it('does not happen when the server says this user may not join', () => {
      // Guests, anyone without the widget permission, and anyone who has opted out of
      // being shown as online without the permission to see everyone anyway.
      app.forum.pushAttributes({ canJoinOnlineUsersPresence: false });

      state.widgetCreated();

      expect(subscribe).not.toHaveBeenCalled();
    });
  });

  describe('events on the channel', () => {
    it('listens for members being added, and for nothing else', () => {
      state.widgetCreated();

      // Someone leaving does not make them stop being online: the server counts
      // anyone seen recently, so a refetch for it would return the same list. And the
      // page already carries the server's answer at connect, so there is no handler for
      // the subscription succeeding either.
      expect(channel.bind).toHaveBeenCalledTimes(1);
      expect(channel.bind).toHaveBeenCalledWith('pusher:member_added', expect.any(Function));
    });

    it('treat a member being added as a sign the list has changed', () => {
      state.widgetCreated();

      expect(presenceChanged).not.toHaveBeenCalled();

      memberAddedHandler()();

      expect(presenceChanged).toHaveBeenCalledTimes(1);
    });
  });

  describe('reconnecting', () => {
    it('does not bind the same channel twice when it is announced again', () => {
      state.widgetCreated();

      realtimeState.notifyUserChannelReady({});
      realtimeState.notifyUserChannelReady({});

      expect(channel.bind).toHaveBeenCalledTimes(1);
    });

    it('does not treat the first channel as a reconnect', () => {
      state.widgetCreated();

      expect(presenceChanged).not.toHaveBeenCalled();
    });

    it('binds the new channel, and assumes events were missed while the socket was down', () => {
      state.widgetCreated();
      const first = channel;

      // A reconnect hands over a fresh channel; the old one is inert.
      channel = newChannel();
      realtimeState.notifyUserChannelReady({});

      expect(subscribe).toHaveBeenCalledTimes(2);
      expect(first.bind).toHaveBeenCalledTimes(1);
      expect(channel.bind).toHaveBeenCalledTimes(1);
      expect(presenceChanged).toHaveBeenCalledTimes(1);
    });
  });
});
