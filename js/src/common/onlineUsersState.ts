import app from 'flarum/common/app';

/**
 * A widget that mounts refetches when the data on the page is older than this.
 * It matches the default `cache_ttl`: asking sooner would mostly get the same
 * cached answer back.
 */
const STALE_AFTER = 30_000;

/**
 * Delay between a presence event and the refetch it triggers: a random value in
 * [MIN_DELAY, MIN_DELAY + SPREAD). The random part stops every subscriber asking
 * in the same instant. MIN_DELAY must not be shorter than the server's
 * `UserRepository::FRESH_WINDOW` (2s): a refetch is then always at least one
 * window after the event, so the answer it gets was computed after the event.
 */
const MIN_DELAY = 2_000;
const SPREAD = 3_000;

/**
 * Keeps the online users list on the forum model up to date.
 *
 * The server is the single source of truth. This only decides *when to ask it
 * again*, and a list is out of date when either
 *
 * - it was fetched more than {@link STALE_AFTER} ago, which covers navigating away
 *   and coming back (and forums without realtime, where nothing else would ever
 *   refresh it), or
 * - a presence event said it has probably changed and it has not been refetched
 *   since. Events only count while a widget is mounted; one that arrives while the
 *   widget is off screen stays pending, and the refetch happens when it returns.
 */
export class OnlineUsersState {
  protected fetchedAt = Date.now();

  /** Widgets currently on screen. */
  protected mounted = 0;
  protected hasBeenMounted = false;
  protected firstMountCallbacks: (() => void)[] = [];

  /** When each presence event not yet reflected in the list arrived. */
  protected pendingEvents: number[] = [];
  protected timer: ReturnType<typeof setTimeout> | null = null;

  /** Request ordering: a response is dropped if a later request already landed. */
  protected started = 0;
  protected applied = 0;

  /**
   * Whether a widget has ever mounted. Realtime uses this to join the presence
   * channel only once there is something to keep up to date.
   */
  get wanted(): boolean {
    return this.hasBeenMounted;
  }

  /**
   * Run `callback` when the first widget mounts (immediately if one already has).
   */
  onFirstMount(callback: () => void): void {
    if (this.hasBeenMounted) {
      callback();
    } else {
      this.firstMountCallbacks.push(callback);
    }
  }

  /**
   * Called when an online users widget mounts. Navigating back to a page that
   * shows it lands here, which is what makes it catch up.
   */
  widgetCreated(): void {
    this.mounted++;

    if (!this.hasBeenMounted) {
      this.hasBeenMounted = true;
      this.firstMountCallbacks.splice(0).forEach((callback) => callback());
    }

    if (this.isStale()) {
      // Events that arrived while the widget was away mean the cached answer is
      // suspect, so skip it; otherwise the normal cache is fine.
      this.refresh(this.pendingEvents.length > 0);
    }
  }

  widgetRemoved(): void {
    this.mounted = Math.max(0, this.mounted - 1);

    // Nothing is on screen to update. Pending events are kept, so the refetch
    // happens when a widget next mounts.
    if (this.mounted === 0 && this.timer !== null) {
      clearTimeout(this.timer);
      this.timer = null;
    }
  }

  /**
   * Presence says someone joined (or the socket reconnected and events may have
   * been missed): the list has probably changed.
   *
   * Coalesced rather than debounced. A trailing debounce restarts on every event,
   * so steady churn would keep postponing the refetch indefinitely; here the first
   * event arms the timer and later ones ride on it.
   */
  presenceChanged(): void {
    this.pendingEvents.push(Date.now());

    if (this.mounted > 0) this.scheduleRefresh();
  }

  isStale(): boolean {
    return this.pendingEvents.length > 0 || Date.now() - this.fetchedAt > STALE_AFTER;
  }

  protected scheduleRefresh(): void {
    if (this.timer !== null) return;

    this.timer = setTimeout(
      () => {
        this.timer = null;

        if (this.mounted > 0) this.refresh(true);
      },
      MIN_DELAY + Math.random() * SPREAD
    );
  }

  /**
   * Refetch the forum resource, which carries `onlineUsers` and
   * `totalOnlineUsers`.
   *
   * `store.find('forum')` cannot be used: it would build `apiUrl + '/forum'`,
   * whereas the forum resource is served from the API root. Pushing the payload
   * replaces the `onlineUsers` relationship wholesale rather than merging into
   * it, so users correctly disappear once the server stops returning them.
   *
   * `include` is narrowed so the response carries only the online users, not the
   * forum's other default includes.
   *
   * This runs in the background, so a failure is swallowed rather than alerting
   * the user about something they did not ask for; the list simply stays as it
   * was and is still considered stale, so the next mount tries again.
   *
   * @param fresh ask the server to skip its cached answer; see
   *              `UserRepository::FRESH_WINDOW`.
   */
  async refresh(fresh: boolean): Promise<void> {
    const startedAt = Date.now();
    const token = ++this.started;

    try {
      const payload = await app.request<any>({
        method: 'GET',
        url: app.forum.attribute<string>('apiUrl') + '/',
        params: fresh ? { include: 'onlineUsers', onlineUsersFresh: 1 } : { include: 'onlineUsers' },
        errorHandler: () => {},
      });

      // A slower, older request resolving after a newer one has landed would
      // overwrite newer data with older.
      if (token < this.applied) return;
      this.applied = token;

      app.store.pushPayload(payload);

      this.fetchedAt = Date.now();

      // Only events at least a window old are known to be in this answer; later
      // ones may have missed it and stay pending for another refetch.
      this.pendingEvents = this.pendingEvents.filter((arrivedAt) => arrivedAt > startedAt - MIN_DELAY);

      m.redraw();

      if (this.pendingEvents.length > 0 && this.mounted > 0) this.scheduleRefresh();
    } catch {
      // See above.
    }
  }
}

// Bound to a name before exporting: flarum-webpack-config's autoExportLoader
// reads the identifier following the default-export keyword to register the
// module, so exporting `new …` inline breaks the build. It scans the whole file
// including comments, so do not write that keyword pair in one.
const onlineUsersState = new OnlineUsersState();

export default onlineUsersState;
