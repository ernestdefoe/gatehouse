import app from 'flarum/common/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/helpers/humanTime';
import type Applicant from '../models/Applicant';
import canDecide from '../canDecide';
import type Mithril from 'mithril';

type Tab = 'pending' | 'declined' | 'approved';
type Decision = 'approved' | 'declined';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-gatehouse.lib.queue.${key}`, params);
const TABS: Tab[] = ['pending', 'declined', 'approved'];

/**
 * The applicants: waiting, declined and recently approved, with Approve / Decline.
 * Shared by the admin panel and the forum's /gatehouse page.
 */
export default class Queue extends Component {
  tab: Tab = 'pending';
  rows: Applicant[] | null = null;
  hasMore = false;
  loadingMore = false;
  pending = 0;
  busy: Record<string, Decision> = {};

  oninit(vnode: Mithril.Vnode) {
    super.oninit(vnode);
    this.pending = app.forum.attribute<number>('gatehousePending') || 0;
    this.load();
  }

  // A page at a time. Deciding removes a row from this tab's list, so the rows
  // already shown are exactly the offset of the next page.
  load(more = false) {
    const tab = this.tab;
    const offset = more && this.rows ? this.rows.length : 0;
    if (more) this.loadingMore = true;
    else this.rows = null;

    app.store
      .find<Applicant[]>('gatehouse-applicants', { filter: { status: tab }, page: { offset } })
      .then((results) => {
        if (tab !== this.tab) return;
        this.rows = offset && this.rows ? this.rows.concat(results) : results;
        this.hasMore = !!results.payload?.links?.next;
        if (tab === 'pending') this.setPending(results.payload?.meta?.page?.total ?? this.rows.length);
      })
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => {
        this.loadingMore = false;
        m.redraw();
      });
  }

  // Keeps the forum's badge in step with the queue.
  setPending(count: number) {
    this.pending = count;
    if (canDecide()) app.forum.pushAttributes({ gatehousePending: count });
  }

  decide(row: Applicant, decision: Decision) {
    const id = row.id()!;
    this.busy[id] = decision;

    row
      .save({ status: decision })
      .then(() => {
        this.rows = (this.rows || []).filter((r) => r !== row);
        if (this.tab === 'pending') this.setPending(Math.max(0, this.pending - 1));
      })
      .catch(() => {
        /* app.request already showed the alert */
      })
      .finally(() => {
        delete this.busy[id];
        m.redraw();
      });
  }

  view() {
    return (
      <div className="GatehouseQueue">
        {this.tabs()}
        {this.list()}
        {this.rows && this.hasMore ? (
          <div className="GatehouseQueue-more">
            <Button className="Button" loading={this.loadingMore} onclick={() => this.load(true)}>
              {t('load_more')}
            </Button>
          </div>
        ) : null}
      </div>
    );
  }

  tabs() {
    return (
      <div className="GatehouseQueue-tabs" role="tablist">
        {TABS.map((tab) => (
          <button
            type="button"
            role="tab"
            aria-selected={tab === this.tab ? 'true' : 'false'}
            className={tab === this.tab ? 'active' : ''}
            onclick={() => {
              this.tab = tab;
              this.load();
            }}
          >
            {t(`tab_${tab}`)}
            {tab === 'pending' && this.pending ? <span className="GatehouseQueue-count">{this.pending}</span> : null}
          </button>
        ))}
      </div>
    );
  }

  list() {
    if (this.rows === null) return <LoadingIndicator />;
    if (this.rows.length === 0) return <p className="GatehouseQueue-empty">{t(`empty_${this.tab}`)}</p>;

    return <ul className="GatehouseQueue-list">{this.rows.map((row) => this.row(row))}</ul>;
  }

  row(row: Applicant) {
    const reason = row.reason();

    return (
      <li className="GatehouseQueue-row" key={row.id()}>
        <div className="GatehouseQueue-who">
          <strong>{row.displayName()}</strong>
          {row.username() !== row.displayName() ? <span className="GatehouseQueue-username">@{row.username()}</span> : null}
          {row.email() ? <span className="GatehouseQueue-email">{row.email()}</span> : null}
          {reason ? (
            <blockquote className="GatehouseQueue-reason" title={t('answer')}>
              {reason}
            </blockquote>
          ) : null}
          <span className="GatehouseQueue-meta">{this.when(row)}</span>
        </div>
        <div className="GatehouseQueue-actions">{this.actions(row)}</div>
      </li>
    );
  }

  when(row: Applicant) {
    const heldAt = row.heldAt();
    const decidedAt = row.decidedAt();

    if (this.tab === 'pending') return [t('signed_up'), ' ', heldAt ? humanTime(heldAt) : null];

    return [
      t(this.tab === 'approved' ? 'approved_by' : 'declined_by', { name: row.decidedBy() || '?' }),
      ' ',
      decidedAt ? humanTime(decidedAt) : null,
    ];
  }

  actions(row: Applicant) {
    const busy = this.busy[row.id()!];
    const button = (decision: Decision, className: string, icon: string, label: string) => (
      <Button className={className} icon={icon} loading={busy === decision} disabled={!!busy} onclick={() => this.decide(row, decision)}>
        {t(label)}
      </Button>
    );

    return [
      this.tab !== 'approved' ? button('approved', 'Button Button--primary', 'fas fa-check', 'approve') : null,
      this.tab === 'pending' ? button('declined', 'Button', 'fas fa-xmark', 'decline') : null,
    ];
  }
}
