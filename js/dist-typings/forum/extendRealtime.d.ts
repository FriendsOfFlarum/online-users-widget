/**
 * Only called when flarum-realtime is enabled — see the guard in forum/index.ts.
 * The `ext:` import above compiles to a webpack external whose factory runs on
 * first require, so nothing is resolved on forums without realtime installed.
 */
export default function extendRealtime(): void;
