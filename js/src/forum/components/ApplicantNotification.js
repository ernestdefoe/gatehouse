import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';

/** "{name} signed up and is waiting for approval" — links to the forum's queue page. */
export default class ApplicantNotification extends Notification {
  icon() {
    return 'fas fa-dungeon';
  }

  href() {
    return app.route('gatehouse.applicants');
  }

  content() {
    return app.translator.trans('ernestdefoe-gatehouse.forum.notification', { name: this.attrs.notification.fromUser()?.displayName() });
  }

  // Their answer to the sign-up question, when there is one.
  excerpt() {
    return this.attrs.notification.fromUser()?.attribute('gatehouseReason') || null;
  }
}
