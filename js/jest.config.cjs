const base = require('@flarum/jest-config')();

/**
 * `@flarum/jest-config` maps `flarum/*` to core's source, but not `ext:` imports,
 * which webpack turns into lookups in the export registry of another extension's
 * bundle. Those bundles are not available under Jest, so the two extensions this
 * one imports from are replaced by the small stand-ins in `tests/stubs`.
 */
module.exports = {
  ...base,
  moduleNameMapper: {
    ...base.moduleNameMapper,
    '^ext:fof/forum-widgets-core/(.*)$': '<rootDir>/tests/stubs/forum-widgets-core/$1',
    '^ext:flarum/realtime/(.*)$': '<rootDir>/tests/stubs/realtime/$1',
  },
};
