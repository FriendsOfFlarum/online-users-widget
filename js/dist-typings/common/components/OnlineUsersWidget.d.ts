import type Mithril from 'mithril';
import Widget, { type WidgetAttrs } from 'ext:fof/forum-widgets-core/common/components/Widget';
export default class OnlineUsersWidget extends Widget<WidgetAttrs> {
    oncreate(vnode: Mithril.VnodeDOM<WidgetAttrs, this>): void;
    onremove(vnode: Mithril.VnodeDOM<WidgetAttrs, this>): void;
    className(): string;
    icon(): string;
    title(): string;
    content(): Mithril.Children;
    /**
     * The "+N" chip. Its text alone ("+3") means nothing to a screen reader, so the
     * label says what it counts.
     */
    protected moreChip(count: number): Mithril.Children;
}
