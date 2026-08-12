interface OnlineUsersState {
    /**
     * Whether a presence-triggered refetch is in flight. Read by the widget to
     * show a loading indicator on the first load only — later refetches swap
     * results in without blanking the list.
     */
    isLoading: boolean;
}
declare const state: OnlineUsersState;
export default state;
