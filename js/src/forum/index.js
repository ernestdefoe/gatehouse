import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Notices from 'flarum/forum/components/Notices';
import Alert from 'flarum/common/components/Alert';
import ApplicantNotification from './components/ApplicantNotification';

app.initializers.add('ernestdefoe-gatehouse', () => {
  app.notificationComponents.gatehouseApplicant = ApplicantNotification;

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
