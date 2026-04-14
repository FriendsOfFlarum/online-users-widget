import app from 'flarum/forum/app';
import type { Channel } from 'pusher-js';
import RealtimeState from 'ext:flarum/realtime/forum/RealtimeState';

import onlineUsersState from '../common/onlineUsersState';

interface PresenceMembers {
  each(callback: (member: { id: string; info: Record<string, unknown> }) => void): void;
}

async function ensureUserLoaded(id: string): Promise<void> {
  if (!app.store.getById('users', id)) {
    await app.store.find<any>('users', id);
  }
}

function subscribeToPresence(): void {
  if (!app.session.user) {
    return;
  }

  const presenceChannel: Channel = app.websocket.subscribe('presence-online');

  presenceChannel.bind('pusher:subscription_succeeded', async (members: PresenceMembers) => {
    onlineUsersState.userIds = new Set<string>();

    const loads: Promise<void>[] = [];
    members.each((member) => {
      onlineUsersState.userIds.add(member.id);
      loads.push(ensureUserLoaded(member.id));
    });

    await Promise.all(loads);

    onlineUsersState.total = onlineUsersState.userIds.size;

    if (app.forum.attribute<boolean>('canViewOnlineUsersWidget')) {
      onlineUsersState.realtimeActive = true;
      m.redraw();
    }
  });

  presenceChannel.bind('pusher:member_added', async (member: { id: string }) => {
    // Increment total immediately (includes users not yet rendered due to privacy prefs etc).
    onlineUsersState.total++;
    if (onlineUsersState.realtimeActive) {
      await ensureUserLoaded(member.id);
      // Only add to the render set once the model is in the store.
      onlineUsersState.userIds.add(member.id);
      m.redraw();
    }
  });

  presenceChannel.bind('pusher:member_removed', (member: { id: string }) => {
    onlineUsersState.userIds.delete(member.id);
    onlineUsersState.total = onlineUsersState.userIds.size;
    if (onlineUsersState.realtimeActive) {
      m.redraw();
    }
  });
}

export default function extendRealtime(): void {
  // Logged-in users only get a private user channel (no public channel).
  // Guests only get the public channel. Use whichever fires — both signal
  // that Pusher is connected and app.forum is populated.
  RealtimeState.onUserChannelReady(subscribeToPresence);
  RealtimeState.onPublicChannelReady(subscribeToPresence);
}
