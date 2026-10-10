import { type OnlineUsersState } from '../common/onlineUsersState';
/**
 * Presence is used purely as an invalidation signal. The server remains the
 * single source of truth for who is online and what may be shown of them.
 *
 * It is tempting to render the presence roster directly — it is, after all, an
 * exact list of who holds a socket right now. But presence membership is the
 * *subscriber* list, and subscribing requires `viewOnlineUsersWidget`. On a
 * forum where that permission is restricted, the roster is not "everyone
 * online", it is "everyone online who may view the widget" — a far smaller set.
 * Treating it as authoritative therefore made the list collapse the moment the
 * socket connected: the page payload counted every recently-active user, then
 * the roster replaced it with the handful of permitted subscribers.
 *
 * The client also cannot filter a roster correctly even when it is complete: it
 * cannot apply the visibility scope, cannot honour the `discloseOnline`
 * preference (the presence payload carries only a display name), and cannot
 * apply the `max_users` cap the `+N` chip is derived from.
 *
 * So membership changes only tell us "something moved, ask again" — see
 * onlineUsersState.ts for when and how. Only arrivals are listened for: the
 * server defines "online" as recently seen, so someone leaving the channel does
 * not make them stop being online, and a refetch for it would return the same
 * list.
 */
/**
 * Only called when flarum-realtime is enabled — see the guard in forum/index.ts.
 * The `ext:` import above compiles to a webpack external whose factory runs on
 * first require, so nothing is resolved on forums without realtime installed.
 *
 * What it tracks (the channel it last bound to) lives in this call rather than the
 * module, so each call starts from nothing. It runs once per page; `state` is the
 * page's shared one unless a test passes its own.
 */
export default function extendRealtime(state?: OnlineUsersState): void;
