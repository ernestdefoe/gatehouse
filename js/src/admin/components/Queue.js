import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/helpers/humanTime';

const t = (key, params) => app.translator.trans(`ernestdefoe-gatehouse.admin.queue.${key}`, params);
const TABS = ['pending', 'declined', 'approved'];

/** The applicants: waiting, declined and recently approved, with Approve / Decline. */
export default class Queue extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.tab = 'pending';
    this.rows = null;
    this.pending = 0;
    this.busy = {};
    this.load();
  }

  load() {
    this.rows = null;
    app
      .request({ method: 'GET', url: app.forum.attribute('apiUrl') + '/gatehouse/queue', params: { status: this.tab } })
      .then((r) => {
        this.rows = r.applicants;
        this.pending = r.counts.pending;
        m.redraw();
      });
  }

  decide(row, decision) {
    this.busy[row.id] = decision;
    app
      .request({ method: 'POST', url: `${app.forum.attribute('apiUrl')}/gatehouse/applicants/${row.id}/${decision}` })
      .then(() => {
        this.rows = this.rows.filter((r) => r.id !== row.id);
        if (this.tab === 'pending') this.pending = Math.max(0, this.pending - 1);
      })
      .finally(() => {
        delete this.busy[row.id];
        m.redraw();
      });
  }

  view() {
    return (
      <div className="GatehouseQueue">
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

        {this.rows === null ? (
          <LoadingIndicator />
        ) : this.rows.length === 0 ? (
          <p className="GatehouseQueue-empty">{t(`empty_${this.tab}`)}</p>
        ) : (
          <ul className="GatehouseQueue-list">
            {this.rows.map((row) => (
              <li className="GatehouseQueue-row" key={row.id}>
                <div className="GatehouseQueue-who">
                  <strong>{row.displayName}</strong>
                  <span className="GatehouseQueue-email">{row.email}</span>
                  <span className="GatehouseQueue-meta">
                    {this.tab === 'pending'
                      ? [t('signed_up'), ' ', humanTime(row.joinedAt)]
                      : [t(this.tab === 'approved' ? 'approved_by' : 'declined_by', { name: row.decidedBy || '?' }), ' ', row.decidedAt ? humanTime(row.decidedAt) : null]}
                  </span>
                </div>
                <div className="GatehouseQueue-actions">
                  {this.tab !== 'approved' ? (
                    <Button className="Button Button--primary" icon="fas fa-check" loading={this.busy[row.id] === 'approve'} disabled={!!this.busy[row.id]} onclick={() => this.decide(row, 'approve')}>
                      {t('approve')}
                    </Button>
                  ) : null}
                  {this.tab === 'pending' ? (
                    <Button className="Button" icon="fas fa-xmark" loading={this.busy[row.id] === 'decline'} disabled={!!this.busy[row.id]} onclick={() => this.decide(row, 'decline')}>
                      {t('decline')}
                    </Button>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
    );
  }
}
