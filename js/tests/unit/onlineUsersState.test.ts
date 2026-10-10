import { jest } from '@jest/globals';
import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import { OnlineUsersState } from '../../src/common/onlineUsersState';

beforeAll(() => bootstrapForum());

/**
 * `OnlineUsersState` decides *when to ask the server again*; the server stays the
 * only source of truth for who is online. So these tests are about timing and
 * bookkeeping: what is requested, when, how often, and what is done with the answer.
 *
 * Time is faked, so "31 seconds later" costs nothing, and each test gets a fresh
 * instance (the default export is one shared instance for the page).
 */
describe('OnlineUsersState', () => {
  let state: OnlineUsersState;
  let request: any;
  let pushPayload: any;
  let find: any;

  /** What `app.request` resolves with, per call. */
  let respond: (options: any) => Promise<any>;

  const advance = (ms: number) => jest.advanceTimersByTimeAsync(ms);

  /** A request that has been made, with the params it carried. */
  const params = (call = 0) => request.mock.calls[call][0].params;

  beforeAll(() => {
    app.boot();
    app.forum.pushAttributes({ apiUrl: 'https://forum.test/api' });
  });

  beforeEach(() => {
    jest.useFakeTimers({ now: 1_000_000 });

    respond = async () => ({ data: {} });

    request = jest.spyOn(app, 'request').mockImplementation(((options: any) => respond(options)) as any);
    pushPayload = jest.spyOn(app.store, 'pushPayload').mockImplementation((() => ({})) as any);
    find = jest.spyOn(app.store, 'find').mockImplementation((async () => ({})) as any);

    state = new OnlineUsersState();
  });

  afterEach(() => {
    jest.restoreAllMocks();
    jest.useRealTimers();
  });

  describe('when a widget mounts', () => {
    it('does not refetch data that has just been loaded', async () => {
      state.widgetCreated();
      await advance(0);

      expect(request).not.toHaveBeenCalled();
    });

    it('refetches through the normal cache once the data is more than 30 seconds old', async () => {
      await advance(31_000);

      state.widgetCreated();
      await advance(0);

      expect(request).toHaveBeenCalledTimes(1);
      expect(params()).toEqual({ include: 'onlineUsers' });
      expect(pushPayload).toHaveBeenCalledTimes(1);
    });

    it('catches up when the user navigates away and comes back later', async () => {
      state.widgetCreated();
      state.widgetRemoved();

      await advance(31_000);

      state.widgetCreated();
      await advance(0);

      expect(request).toHaveBeenCalledTimes(1);
    });

    it('asks the server to skip its cache when a presence event arrived while the widget was away', async () => {
      state.presenceChanged();
      await advance(10_000);

      // Nothing on screen to update, so nothing was asked.
      expect(request).not.toHaveBeenCalled();

      state.widgetCreated();
      await advance(0);

      expect(request).toHaveBeenCalledTimes(1);
      expect(params()).toEqual({ include: 'onlineUsers', onlineUsersFresh: 1 });
    });
  });

  describe('presence events while a widget is mounted', () => {
    it('are coalesced into one request, asking the server to skip its cache', async () => {
      state.widgetCreated();

      state.presenceChanged();
      await advance(500);
      state.presenceChanged();
      await advance(500);
      state.presenceChanged();

      expect(request).not.toHaveBeenCalled();

      await advance(6_000);

      expect(request).toHaveBeenCalledTimes(1);
      expect(params()).toEqual({ include: 'onlineUsers', onlineUsersFresh: 1 });
    });

    it('never trigger a refetch sooner than 2 seconds after the event', async () => {
      // The delay is 2 seconds plus a random spread; pin the spread to nothing so this
      // tests the floor, which is what the server's 2 second window depends on.
      jest.spyOn(Math, 'random').mockReturnValue(0);

      state.widgetCreated();
      state.presenceChanged();

      // The server answers fresh requests from a 2 second window; a refetch earlier than
      // that could land in a window that began before the event.
      await advance(1_999);

      expect(request).not.toHaveBeenCalled();
    });

    it('do not starve the refetch when they keep coming', async () => {
      state.widgetCreated();

      for (let i = 0; i < 20; i++) {
        state.presenceChanged();
        await advance(1_000);
      }

      expect(request.mock.calls.length).toBeGreaterThanOrEqual(3);
    });

    it('stay pending when the widget goes away before the refetch, and are not requested while it is gone', async () => {
      state.widgetCreated();
      state.presenceChanged();
      state.widgetRemoved();

      await advance(10_000);

      expect(request).not.toHaveBeenCalled();
      expect(state.isStale()).toBe(true);
    });

    it('from less than 2 seconds before a refetch started are not counted as covered by it either', async () => {
      // The server answers fresh requests from a 2 second window, so an answer can be up
      // to that much older than the request that fetched it. An event that close to the
      // start may not be in it.
      jest.spyOn(Math, 'random').mockReturnValue(0);

      state.widgetCreated();
      state.presenceChanged(); // t = 0: the refetch is armed for t = 2s

      await advance(1_500);
      state.presenceChanged(); // t = 1.5s: 0.5s before the request starts

      await advance(500); // t = 2s: the request is made and answered
      expect(request).toHaveBeenCalledTimes(1);

      // The first event is covered; the second may not be, and stays pending.
      expect(state.isStale()).toBe(true);

      await advance(6_000);

      expect(request).toHaveBeenCalledTimes(2);
    });

    it('that arrive after a refetch has started are not counted as covered by it', async () => {
      let finish!: (payload: any) => void;
      respond = () => new Promise((resolve) => (finish = resolve));

      state.widgetCreated();
      state.presenceChanged();

      await advance(5_000); // the timer fires and the request is in flight
      expect(request).toHaveBeenCalledTimes(1);

      await advance(100);
      state.presenceChanged(); // arrives after the request started, so it may have missed it

      respond = async () => ({ data: {} });
      finish({ data: {} });
      await advance(0);

      // Still pending, and gets a refetch of its own.
      expect(state.isStale()).toBe(true);

      await advance(6_000);

      expect(request).toHaveBeenCalledTimes(2);
    });
  });

  describe('a background refetch', () => {
    it('is silent when it fails, and keeps the list stale so the next mount tries again', async () => {
      respond = async () => {
        throw new Error('offline');
      };

      await advance(31_000);
      state.widgetCreated();
      await advance(0);

      expect(request).toHaveBeenCalledTimes(1);

      // A handler that returns anything but `false` keeps core's default alert away.
      const { errorHandler } = request.mock.calls[0][0];
      expect(typeof errorHandler).toBe('function');
      expect(errorHandler()).toBeUndefined();

      expect(state.isStale()).toBe(true);

      respond = async () => ({ data: {} });
      state.widgetRemoved();
      state.widgetCreated();
      await advance(0);

      expect(request).toHaveBeenCalledTimes(2);
      expect(state.isStale()).toBe(false);
    });

    it('is dropped when an older request resolves after a newer one has landed', async () => {
      const resolvers: ((payload: any) => void)[] = [];
      respond = () => new Promise((resolve) => resolvers.push(resolve));

      await advance(31_000);
      state.widgetCreated(); // request 1
      state.widgetRemoved();
      await advance(31_000);
      state.widgetCreated(); // request 2

      resolvers[1]({ n: 'newer' });
      await advance(0);
      resolvers[0]({ n: 'older' });
      await advance(0);

      expect(pushPayload.mock.calls.map((call: any[]) => call[0])).toEqual([{ n: 'newer' }]);
    });
  });

  describe('first mount', () => {
    it('is announced once, and immediately to anyone who asks after it', () => {
      let calls = 0;

      state.onFirstMount(() => calls++);
      expect(state.wanted).toBe(false);

      state.widgetCreated();
      state.widgetRemoved();
      state.widgetCreated();

      expect(calls).toBe(1);
      expect(state.wanted).toBe(true);

      state.onFirstMount(() => calls++);
      expect(calls).toBe(2);
    });
  });

  describe('hydrating a user about to be opened', () => {
    const user = (id: string, slug = `slug${id}`) => ({ id: () => id, slug: () => slug }) as any;

    it('fetches the real profile data for the user, by slug', async () => {
      state.hydrate(user('7', 'bob'));
      await advance(0);

      expect(find).toHaveBeenCalledTimes(1);
      expect(find.mock.calls[0].slice(0, 3)).toEqual(['users', 'bob', { bySlug: true }]);
    });

    it('is silent: the request carries a handler that keeps the default alert away', async () => {
      state.hydrate(user('7'));
      await advance(0);

      const options = find.mock.calls[0][3];

      expect(typeof options.errorHandler).toBe('function');
      expect(options.errorHandler()).toBeUndefined();
    });

    it('fetches each user once', async () => {
      state.hydrate(user('7'));
      state.hydrate(user('7'));
      state.hydrate(user('8'));
      await advance(0);

      expect(find).toHaveBeenCalledTimes(2);
    });

    it('tries a user again on the next click if the fetch failed', async () => {
      find.mockImplementationOnce((async () => {
        throw new Error('offline');
      }) as any);

      state.hydrate(user('7'));
      await advance(0);
      state.hydrate(user('7'));
      await advance(0);

      expect(find).toHaveBeenCalledTimes(2);
    });

    it('does not reject when the fetch fails', async () => {
      find.mockImplementation((async () => {
        throw new Error('offline');
      }) as any);

      state.hydrate(user('7'));

      await expect(advance(0)).resolves.not.toThrow();
    });

    it('leaves the list state and the forum refetch alone', async () => {
      state.hydrate(user('7'));
      await advance(0);

      expect(request).not.toHaveBeenCalled();
      expect(state.isStale()).toBe(false);
    });
  });
});
