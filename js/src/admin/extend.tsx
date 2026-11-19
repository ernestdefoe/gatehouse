import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';
import Queue from '../common/components/Queue';

const t = (key: string) => app.translator.trans(`ernestdefoe-gatehouse.admin.${key}`);
const heading = (key: string) => () => <h3 className="GatehouseAdmin-heading">{t(key)}</h3>;
// Each part is read when the page renders, not when this module loads: the translator isn't ready then.
const field = (key: string, type: string, placeholder?: () => string) => () => ({
  setting: `ernestdefoe-gatehouse.${key}`,
  type,
  label: t(key),
  help: t(`${key}_help`),
  placeholder: placeholder?.(),
});

// Settings are listed by priority, highest first: this is the order on the page.
export default [
  ...commonExtend,

  new Extend.Admin()
    // Admins always have it. Give it to Moderators (or any group) to share the queue.
    .permission(() => ({ icon: 'fas fa-dungeon', label: t('permission_decide'), permission: 'ernestdefoe-gatehouse.decide' }), 'moderate', 90)
    .customSetting(
      () => (
        <div className="Form-group">
          <h3 className="GatehouseAdmin-heading">{t('queue_heading')}</h3>
          <Queue />
        </div>
      ),
      120
    )
    .customSetting(heading('rules_heading'), 110)
    .setting(field('enabled', 'boolean'), 100)
    .setting(
      field('allow', 'textarea', () => '.edu\nuni-freiburg.de\n@staff.example.org'),
      90
    )
    .setting(
      field('deny', 'textarea', () => '*@mailinator.com\n/\\d{6,}@/'),
      80
    )
    .customSetting(heading('usernames_heading'), 70)
    .setting(
      field('reserved_usernames', 'textarea', () => t('reserved_usernames_placeholder') as string),
      60
    )
    .customSetting(heading('question_heading'), 50)
    .setting(
      field('question', 'text', () => t('question_placeholder') as string),
      40
    )
    .setting(field('question_required', 'boolean'), 30)
    .customSetting(heading('messages_heading'), 20)
    .setting(field('held_message', 'textarea'), 10)
    .setting(field('declined_message', 'textarea'), 0),
];
