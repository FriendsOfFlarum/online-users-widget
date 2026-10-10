import app from 'flarum/forum/app';
import type { Channel } from 'pusher-js';
import RealtimeState from 'ext:flarum/realtime/forum/RealtimeState';

import onlineUsersState from '../common/onlineUsersState';

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
 * Channel-ready callbacks re-fire on every reconnect cycle (iOS backgrounding,
 * desktop Safari zombie sockets — see realtime's Application.ts forceReconnect),
 * each time handing over a fresh channel object. Re-binding on the new channel
 * is required; re-binding on one we already hold would stack duplicate
 * handlers, so track the channel we last bound to.
 */
let boundChannel: Channel | null = null;

const onMemberAdded = () => onlineUsersState.presenceChanged();

function subscribeToPresence(): void {
  // Nothing on screen needs keeping up to date yet, so don't announce ourselves
  // to everyone else (and cost them a refetch) for nothing. The first widget to
  // mount calls this again.
  if (!onlineUsersState.wanted) return;

  // Not connected yet; the channel-ready callbacks below call this once it is.
  if (!app.websocket) return;

  // Whether the server would let us in, decided by the same rule as the channel
  // guard (guests, the widget permission, and the `discloseOnline` preference).
  if (!app.forum.attribute<boolean>('canJoinOnlineUsersPresence')) return;

  const channel: Channel = app.websocket.subscribe('presence-online');

  if (channel === boundChannel) return;

  // A channel after the first is a reconnect, and nothing is buffered while the
  // socket is down, so whatever happened in the meantime was missed.
  const reconnected = boundChannel !== null;

  boundChannel = channel;

  // No handler for `pusher:subscription_succeeded`: the page payload is already
  // the server's answer, so there is nothing to correct on connect. Refetching
  // there is what produced the visible collapse.
  channel.bind('pusher:member_added', onMemberAdded);

  if (reconnected) onlineUsersState.presenceChanged();
}

/**
 * Only called when flarum-realtime is enabled — see the guard in forum/index.ts.
 * The `ext:` import above compiles to a webpack external whose factory runs on
 * first require, so nothing is resolved on forums without realtime installed.
 */
export default function extendRealtime(): void {
  // Realtime is flagged enabled but its bundle did not load before ours (an
  // install whose extension order was not re-synced), so the import is undefined.
  if (!RealtimeState) return;

  onlineUsersState.onFirstMount(subscribeToPresence);

  // Logged-in users get a private user channel; guests get the public one.
  // Either firing means Pusher is connected and app.forum is populated.
  RealtimeState.onUserChannelReady(subscribeToPresence);
  RealtimeState.onPublicChannelReady(subscribeToPresence);
}
