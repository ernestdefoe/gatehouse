import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Notices from 'flarum/forum/components/Notices';
import Alert from 'flarum/common/components/Alert';
import Stream from 'flarum/common/utils/Stream';
import LinkButton from 'flarum/common/components/LinkButton';
import ApplicantNotification from './components/ApplicantNotification';
import ApplicantsPage from './components/ApplicantsPage';

// Present only for those who may approve and decline (see extend.php).
const pending = () => app.forum.attribute('gatehousePending');

const queueLink = (count) => (
  <LinkButton icon="fas fa-dungeon" href={app.route('gatehouse.applicants')}>
    {app.translator.trans('ernestdefoe-gatehouse.forum.applicants.link')}
    {count ? <span className="GatehouseQueue-count">{count}</span> : null}
  </LinkButton>
);

app.initializers.add('ernestdefoe-gatehouse', () => {
  app.notificationComponents.gatehouseApplicant = ApplicantNotification;

  /*
   * Bundled, not lazy: measured, webpack's chunk loader (~4 KB) costs every
   * visitor more than the page itself does (forum.js 2.23 KB gz bundled vs
   * 2.59 KB gz with the page split out).
   */
  app.routes['gatehouse.applicants'] = { path: '/applicants', component: ApplicantsPage };

  extend('flarum/forum/components/SessionDropdown', 'items', function (items) {
    if (pending() != null) items.add('gatehouseApplicants', queueLink(pending()), 60);
  });

  // In the sidebar only while somebody is waiting.
  extend('flarum/forum/components/IndexSidebar', 'navItems', function (items) {
    if (pending()) items.add('gatehouseApplicants', queueLink(pending()), 85);
  });

  // Whoever decides chooses how they hear about applicants, like any other notification.
  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items) {
    if (pending() == null) return;

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
