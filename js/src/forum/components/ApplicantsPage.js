import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Queue from '../../common/components/Queue';

const t = (key) => app.translator.trans(`ernestdefoe-gatehouse.forum.applicants.${key}`);

/** /applicants: the queue, for everyone who may approve and decline. */
export default class ApplicantsPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);
    app.setTitle(t('title'));
    app.history.push('gatehouse.applicants', t('title'));
  }

  view() {
    // The server only sends the count to those who may decide.
    const allowed = app.forum.attribute('gatehousePending') != null;

    return (
      <PageStructure className="ApplicantsPage" sidebar={() => <IndexSidebar />}>
        <h2 className="ApplicantsPage-title">{t('title')}</h2>
        {allowed ? <Queue /> : <p className="GatehouseQueue-empty">{t('not_allowed')}</p>}
      </PageStructure>
    );
  }
}
