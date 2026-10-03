import app from 'flarum/admin/app';
import Queue from './components/Queue';

const t = (key, params) => app.translator.trans(`ernestdefoe-gatehouse.admin.${key}`, params);

app.initializers.add('ernestdefoe-gatehouse', () => {
  app.registry
    .for('ernestdefoe-gatehouse')
    .registerSetting(() => (
      <div className="Form-group">
        <h3 className="GatehouseAdmin-heading">{t('queue_heading')}</h3>
        <Queue />
      </div>
    ))
    .registerSetting(() => <h3 className="GatehouseAdmin-heading">{t('rules_heading')}</h3>)
    .registerSetting({ setting: 'ernestdefoe-gatehouse.enabled', type: 'boolean', label: t('enabled'), help: t('enabled_help') })
    .registerSetting({ setting: 'ernestdefoe-gatehouse.allow', type: 'textarea', label: t('allow'), help: t('allow_help'), placeholder: '.edu\nuni-freiburg.de\n@staff.example.org' })
    .registerSetting({ setting: 'ernestdefoe-gatehouse.deny', type: 'textarea', label: t('deny'), help: t('deny_help'), placeholder: '*@mailinator.com\n/\\d{6,}@/' })
    .registerSetting(() => <h3 className="GatehouseAdmin-heading">{t('usernames_heading')}</h3>)
    .registerSetting({ setting: 'ernestdefoe-gatehouse.reserved_usernames', type: 'textarea', label: t('reserved_usernames'), help: t('reserved_usernames_help'), placeholder: 'admin*\nmoderator\nsupport\n*official*' })
    .registerSetting(() => <h3 className="GatehouseAdmin-heading">{t('messages_heading')}</h3>)
    .registerSetting({ setting: 'ernestdefoe-gatehouse.held_message', type: 'textarea', label: t('held_message'), help: t('held_message_help') })
    .registerSetting({ setting: 'ernestdefoe-gatehouse.declined_message', type: 'textarea', label: t('declined_message'), help: t('declined_message_help') });
});
