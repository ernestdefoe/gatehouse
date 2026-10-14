import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';

/** "{name} signed up and is waiting for approval" — links to the queue in the admin panel. */
export default class ApplicantNotification extends Notification {
  icon() {
    return 'fas fa-dungeon';
  }

  href() {
    return app.forum.attribute('adminUrl') + '#/extension/ernestdefoe-gatehouse';
  }

  content() {
    return app.translator.trans('ernestdefoe-gatehouse.forum.notification', { name: this.attrs.notification.fromUser()?.displayName() });
  }

  // Their answer to the sign-up question, when there is one.
  excerpt() {
    return this.attrs.notification.fromUser()?.attribute('gatehouseReason') || null;
  }
}
