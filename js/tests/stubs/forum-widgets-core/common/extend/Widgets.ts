/**
 * Stand-in for `ext:fof/forum-widgets-core/common/extend/Widgets`: records what
 * the extension registers, so a test can look at it.
 */
export default class Widgets {
  static registered: any[] = [];
  static extended: { app: unknown; extension: string }[] = [];

  static reset() {
    Widgets.registered = [];
    Widgets.extended = [];
  }

  add(widget: any) {
    Widgets.registered.push(widget);

    return this;
  }

  extend(app: unknown, extension: string) {
    Widgets.extended.push({ app, extension });

    return this;
  }
}
