import app from 'flarum/forum/app';
import registerWidget from '../common/registerWidget';
import extendRealtime from './extendRealtime';

export { default as extend } from './extend';

app.initializers.add('fof/online-users-widget', () => {
  registerWidget(app);

  if ('flarum-realtime' in flarum.extensions) {
    extendRealtime();
  }
});
