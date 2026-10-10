type ChannelReady = (channel: unknown) => void;

/**
 * Stand-in for `ext:flarum/realtime/forum/RealtimeState`: keeps the callbacks the
 * extension registers, and lets a test play the part of realtime announcing that a
 * channel is ready. As in realtime, the callbacks are retained and run again each
 * time a (re)connected channel is announced.
 */
const realtimeState = {
  userChannelCallbacks: [] as ChannelReady[],
  publicChannelCallbacks: [] as ChannelReady[],

  onUserChannelReady(callback: ChannelReady) {
    this.userChannelCallbacks.push(callback);
  },

  onPublicChannelReady(callback: ChannelReady) {
    this.publicChannelCallbacks.push(callback);
  },

  notifyUserChannelReady(channel: unknown) {
    this.userChannelCallbacks.forEach((callback) => callback(channel));
  },

  notifyPublicChannelReady(channel: unknown) {
    this.publicChannelCallbacks.forEach((callback) => callback(channel));
  },

  reset() {
    this.userChannelCallbacks = [];
    this.publicChannelCallbacks = [];
  },
};

export default realtimeState;
