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
    const applicant = this.attrs.notification.fromUser();

    return app.translator.trans('ernestdefoe-gatehouse.forum.notification', { name: applicant ? applicant.displayName() : '' });
  }

  // Their answer to the sign-up question, when there is one (it travels in the notification).
  excerpt() {
    return this.attrs.notification.content<{ reason?: string } | null>()?.reason || null;
  }
}
