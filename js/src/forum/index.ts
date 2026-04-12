import app from 'flarum/forum/app';
import registerWidget from '../common/registerWidget';

export { default as extend } from './extend';

app.initializers.add('fof/online-users-widget', () => {
  registerWidget(app);
});
