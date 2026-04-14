interface OnlineUsersState {
    /** Whether flarum/realtime presence integration is active. */
    realtimeActive: boolean;
    /** IDs of currently-online users, maintained by presence channel events. */
    userIds: Set<string>;
    /**
     * Total count of online users (including those not shown due to the
     * max_users cap or privacy preferences). Initialised from the forum
     * payload and kept in sync via presence events.
     */
    total: number;
}
declare const state: OnlineUsersState;
export default state;
