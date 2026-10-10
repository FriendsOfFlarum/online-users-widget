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
export declare class OnlineUsersState {
    protected fetchedAt: number;
    /** Widgets currently on screen. */
    protected mounted: number;
    protected hasBeenMounted: boolean;
    protected firstMountCallbacks: (() => void)[];
    /** When each presence event not yet reflected in the list arrived. */
    protected pendingEvents: number[];
    protected timer: ReturnType<typeof setTimeout> | null;
    /** Request ordering: a response is dropped if a later request already landed. */
    protected started: number;
    protected applied: number;
    /**
     * Whether a widget has ever mounted. Realtime uses this to join the presence
     * channel only once there is something to keep up to date.
     */
    get wanted(): boolean;
    /**
     * Run `callback` when the first widget mounts (immediately if one already has).
     */
    onFirstMount(callback: () => void): void;
    /**
     * Called when an online users widget mounts. Navigating back to a page that
     * shows it lands here, which is what makes it catch up.
     */
    widgetCreated(): void;
    widgetRemoved(): void;
    /**
     * Presence says someone joined (or the socket reconnected and events may have
     * been missed): the list has probably changed.
     *
     * Coalesced rather than debounced. A trailing debounce restarts on every event,
     * so steady churn would keep postponing the refetch indefinitely; here the first
     * event arms the timer and later ones ride on it.
     */
    presenceChanged(): void;
    isStale(): boolean;
    protected scheduleRefresh(): void;
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
    refresh(fresh: boolean): Promise<void>;
}
declare const onlineUsersState: OnlineUsersState;
export default onlineUsersState;
