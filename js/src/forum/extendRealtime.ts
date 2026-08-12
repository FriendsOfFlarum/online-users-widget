import app from 'flarum/forum/app';
import { debounce } from 'flarum/common/utils/throttleDebounce';
import type { Channel } from 'pusher-js';
import RealtimeState from 'ext:flarum/realtime/forum/RealtimeState';

import onlineUsersState from '../common/onlineUsersState';

/**
 * Presence membership decides *who* is online; the server decides what may be
 * shown of them.
 *
 * The two halves are both necessary. Presence is the accurate answer to "who is
 * online": a user drops off the instant their socket closes, and an idle user
 * holding a socket stays listed — whereas `last_seen_at` is only written on HTTP
 * requests (throttled to 180s), so it keeps departed users for minutes and
 * evicts idle-but-connected ones. But a presence roster cannot be rendered as
 * it stands: the client cannot apply the visibility scope, cannot honour the
 * `discloseOnline` preference (the presence payload carries only a display
 * name), and cannot apply the `max_users` cap the `+N` chip derives from.
 *
 * So the roster is sent to the server, which filters it and returns the users
 * to render. One source of truth for the rendered list, no visible hand-off
 * between two definitions, and the ids are only ever a filter — a forged roster
 * can narrow what the actor sees, never widen it.
 */

interface PresenceMember {
  id: string;
}

interface PresenceMembers {
  each(callback: (member: PresenceMember) => void): void;
}

/** Presence roster, maintained by the channel events. */
let roster = new Set<string>();

/**
 * Refetch the forum resource, passing the current presence roster.
 *
 * `store.find('forum')` cannot be used: it would build `apiUrl + '/forum'`,
 * whereas the forum resource is served from the API root (`GET /`, see
 * ForumResource::endpoints()). Pushing the payload replaces the `onlineUsers`
 * relationship wholesale rather than merging into it, so users correctly
 * disappear from the list once they are no longer in the roster.
 */
async function refetchOnlineUsers(): Promise<void> {
  onlineUsersState.isLoading = true;

  try {
    const payload = await app.request<any>({
      method: 'GET',
      url: app.forum.attribute<string>('apiUrl') + '/',
      params: { onlineIds: [...roster].join(',') },
    });

    app.store.pushPayload(payload);
  } finally {
    onlineUsersState.isLoading = false;
    m.redraw();
  }
}

/**
 * Membership churn arrives one event per user and a single navigation can
 * produce several in a row, so coalesce them into one request. The server-side
 * cache (`cache_ttl`) absorbs repeat rosters.
 */
const scheduleRefetch = debounce(2000, () => {
  refetchOnlineUsers();
});

/**
 * Channel-ready callbacks re-fire on every reconnect cycle (iOS backgrounding,
 * desktop Safari zombie sockets — see realtime's Application.ts forceReconnect),
 * each time handing over a fresh channel object. Re-binding on the new channel
 * is required; re-binding on one we already hold would stack duplicate
 * handlers, so track the channel we last bound to.
 */
let boundChannel: Channel | null = null;

function subscribeToPresence(): void {
  // Presence auth refuses guests outright, so there is nothing to subscribe to
  // and the last_seen_at fallback in the page payload is what they keep.
  if (!app.session.user) return;

  if (!app.forum.attribute<boolean>('canViewOnlineUsersWidget')) return;

  const channel: Channel = app.websocket.subscribe('presence-online');

  if (channel === boundChannel) return;

  boundChannel = channel;

  channel.bind('pusher:subscription_succeeded', (members: PresenceMembers) => {
    // Rebuild rather than merge: after a reconnect the previous roster may
    // contain users who left while the socket was down.
    roster = new Set<string>();
    members.each((member) => roster.add(member.id));

    // The page payload was built from last_seen_at, so it disagrees with the
    // roster we now hold. Refetch immediately to switch to the accurate answer.
    refetchOnlineUsers();
  });

  channel.bind('pusher:member_added', (member: PresenceMember) => {
    roster.add(member.id);
    scheduleRefetch();
  });

  channel.bind('pusher:member_removed', (member: PresenceMember) => {
    roster.delete(member.id);
    scheduleRefetch();
  });
}

/**
 * Only called when flarum-realtime is enabled — see the guard in forum/index.ts.
 * The `ext:` import above compiles to a webpack external whose factory runs on
 * first require, so nothing is resolved on forums without realtime installed.
 */
export default function extendRealtime(): void {
  // Logged-in users get a private user channel; guests get the public one.
  // Either firing means Pusher is connected and app.forum is populated.
  RealtimeState.onUserChannelReady(subscribeToPresence);
  RealtimeState.onPublicChannelReady(subscribeToPresence);
}
