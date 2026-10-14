import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Notices from 'flarum/forum/components/Notices';
import Alert from 'flarum/common/components/Alert';
import Stream from 'flarum/common/utils/Stream';
import ApplicantNotification from './components/ApplicantNotification';

app.initializers.add('ernestdefoe-gatehouse', () => {
  app.notificationComponents.gatehouseApplicant = ApplicantNotification;

  // Admins choose how they hear about applicants, like any other notification.
  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items) {
    if (!app.session.user?.isAdmin()) return;

    items.add('gatehouseApplicant', {
      name: 'gatehouseApplicant',
      icon: 'fas fa-dungeon',
      label: app.translator.trans('ernestdefoe-gatehouse.forum.notify_applicant_label'),
    });
  });

  /*
   * The admin's own question, on the sign-up form, under the password. The
   * answer goes to whoever decides; the server says whether it is required,
   * since only it knows which addresses will wait.
   */
  extend('flarum/forum/components/SignUpModal', 'oninit', function () {
    this.gatehouseReason = Stream('');
  });

  extend('flarum/forum/components/SignUpModal', 'fields', function (items) {
    const question = app.forum.attribute('gatehouseQuestion');
    if (!question) return;

    const required = app.forum.attribute('gatehouseQuestionRequired');

    items.add(
      'gatehouseReason',
      <div className="Form-group">
        <label className="label" for="gatehouse-reason">
          {question}
          {required ? null : <span className="GatehouseReason-optional"> ({app.translator.trans('ernestdefoe-gatehouse.forum.optional')})</span>}
        </label>
        <textarea
          id="gatehouse-reason"
          className="FormControl"
          name="gatehouseReason"
          rows="3"
          maxlength="1000"
          bidi={this.gatehouseReason}
          disabled={this.loading}
        />
      </div>,
      5
    );
  });

  extend('flarum/forum/components/SignUpModal', 'submitData', function (data) {
    if (app.forum.attribute('gatehouseQuestion')) data.gatehouseReason = this.gatehouseReason();
  });

  /*
   * A member waiting for approval sees why they can't do anything yet, in
   * place of core's "confirm your email / resend" notice. That notice would
   * offer them the activation link Gatehouse is deliberately holding back.
   */
  extend(Notices.prototype, 'items', function (items) {
    const status = app.forum.attribute('gatehouseStatus');
    if (!status) return;

    if (items.has('emailConfirmation')) items.remove('emailConfirmation');

    items.add(
      'gatehouse',
      <Alert dismissible={false} className="Alert--gatehouse" containerClassName="container">
        {app.translator.trans(`ernestdefoe-gatehouse.forum.notice_${status}`)}
      </Alert>,
      100
    );
  });
});
