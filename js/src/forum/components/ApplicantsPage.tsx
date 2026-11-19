import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import Queue from '../../common/components/Queue';
import canDecide from '../../common/canDecide';
import type Mithril from 'mithril';

const t = (key: string) => app.translator.trans(`ernestdefoe-gatehouse.forum.applicants.${key}`);

/** /gatehouse: the queue, for everyone who may approve and decline. */
export default class ApplicantsPage extends Page {
  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    app.setTitle(t('title') as string);
    app.history.push('gatehouse.applicants', t('title') as string);
  }

  view() {
    return (
      <PageStructure className="ApplicantsPage" sidebar={() => <IndexSidebar />}>
        <h2 className="ApplicantsPage-title">{t('title')}</h2>
        {canDecide() ? <Queue /> : <p className="GatehouseQueue-empty">{t('not_allowed')}</p>}
      </PageStructure>
    );
  }
}
