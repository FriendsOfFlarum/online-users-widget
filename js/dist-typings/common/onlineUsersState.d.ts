import type User from 'flarum/common/models/User';
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
    /** Users whose profile data has been fetched, so a second click does not repeat it. */
    protected hydrated: Set<string>;
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
    /**
     * Fetch the user's real profile data, ahead of their profile opening.
     *
     * The online users come from the forum payload, which carries a user's own
     * attributes but none of their relationships. Core's profile page reuses any user
     * in the store that has `joinTime` rather than fetching, so opened from here it
     * would render from that bare record, without whatever the profile is built from
     * that is not an attribute: the group badges, and anything an extension adds.
     *
     * Asking the user endpoint settles that for every extension at once. It applies
     * the includes and eager loads that are registered for a profile, whoever
     * registered them, so nothing here needs to know what they are, and the response
     * updates the same record the profile is showing.
     *
     * It runs in the background and is silent on failure; a failed fetch is retried on
     * the next click.
     */
    hydrate(user: User): void;
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
