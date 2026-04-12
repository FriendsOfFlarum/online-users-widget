import type Mithril from 'mithril';
import Widget, { type WidgetAttrs } from 'ext:fof/forum-widgets-core/common/components/Widget';
export default class OnlineUsersWidget extends Widget<WidgetAttrs> {
    className(): string;
    icon(): string;
    title(): string;
    content(): Mithril.Children;
}
