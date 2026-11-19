import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Notices from 'flarum/forum/components/Notices';
import Alert from 'flarum/common/components/Alert';
import Stream from 'flarum/common/utils/Stream';
import LinkButton from 'flarum/common/components/LinkButton';
import type ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

export { default as extend } from './extend';

// Present only for those who may approve and decline (see ForumResourceFields).
const pending = () => app.forum.attribute<number | undefined>('gatehousePending');

const queueLink = (count: number | undefined) => (
  <LinkButton icon="fas fa-dungeon" href={app.route('gatehouse.applicants')}>
    {app.translator.trans('ernestdefoe-gatehouse.forum.applicants.link')}
    {count ? <span className="GatehouseQueue-count">{count}</span> : null}
  </LinkButton>
);

app.initializers.add('ernestdefoe-gatehouse', () => {
  extend('flarum/forum/components/SessionDropdown', 'items', function (items: ItemList<Mithril.Children>) {
    if (pending() != null) items.add('gatehouseApplicants', queueLink(pending()), 60);
  });

  // In the sidebar only while somebody is waiting.
  extend('flarum/forum/components/IndexSidebar', 'navItems', function (items: ItemList<Mithril.Children>) {
    if (pending()) items.add('gatehouseApplicants', queueLink(pending()), 85);
  });

  // Whoever decides chooses how they hear about applicants, like any other notification.
  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items: ItemList<any>) {
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
  extend('flarum/forum/components/SignUpModal', 'oninit', function (this: any) {
    this.gatehouseReason = Stream('');
  });

  extend('flarum/forum/components/SignUpModal', 'fields', function (this: any, items: ItemList<Mithril.Children>) {
    const question = app.forum.attribute<string | null>('gatehouseQuestion');
    if (!question) return;

    const required = app.forum.attribute<boolean>('gatehouseQuestionRequired');

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

  extend('flarum/forum/components/SignUpModal', 'submitData', function (this: any, data: Record<string, unknown>) {
    if (app.forum.attribute('gatehouseQuestion')) data.gatehouseReason = this.gatehouseReason();
  });

  /*
   * A member waiting for approval sees why they can't do anything yet, in
   * place of core's "confirm your email / resend" notice. That notice would
   * offer them the activation link Gatehouse is deliberately holding back.
   */
  extend(Notices.prototype, 'items', function (items: ItemList<Mithril.Children>) {
    const status = app.forum.attribute<string | null>('gatehouseStatus');
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
