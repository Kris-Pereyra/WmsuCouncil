import { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { Link, navigate, qs } from '../router.jsx';
import { useSession } from '../session.jsx';
import { Async, Empty, ErrorList, Head, Pill, money } from '../components/ui.jsx';

const STATUSES = ['Pending', 'Approved', 'Declined'];

/** Officer: "Send to Mayor" form (was requests.php?view=new). */
function NewRequest({ defaultYear, onSaved }) {
  const { values, bind } = useForm({
    request_type: 'Event', event_title: '', event_date: '', school_year: defaultYear,
    venue: '', purpose: '', amount_requested: '',
  });
  const { errors, busy, run } = useSubmit();

  const submit = (e) => {
    e.preventDefault();
    run(async () => {
      await api.post('requests', { do: 'save', ...values });   // the API answers with a flash + redirect to /requests
      onSaved();
    });
  };

  return (
    <section className="card"><h2>Submit request</h2>
      <ErrorList errors={errors} />
      <form className="stack" onSubmit={submit}>
        <label>Request type<select {...bind('request_type')}><option>Event</option><option>Project</option><option>Donation</option><option>Funding</option></select></label>
        <label>Title<input {...bind('event_title')} maxLength={120} required /></label>
        <label>Activity date<input type="date" {...bind('event_date')} required /></label>
        <label>School year<input {...bind('school_year')} maxLength={9} required placeholder="2026-2027" /></label>
        <label>Venue (optional)<input {...bind('venue')} maxLength={120} /></label>
        <label>Purpose and details<textarea {...bind('purpose')} minLength={10} maxLength={500} required /></label>
        <label>Amount requested (₱)<input type="number" {...bind('amount_requested')} min="0.01" step="0.01" required /></label>
        <div><button className="btn" type="submit" disabled={busy}>Send to Mayor</button> <Link className="btn ghost" to="/requests">Cancel</Link></div>
      </form>
    </section>
  );
}

/** Mayor / admin: approve or decline one pending request. */
function DecideForm({ id, run, onDone }) {
  const [remarks, setRemarks] = useState('');
  const decide = (decision) => run(async () => {
    await api.post('requests', { do: 'decide', id, decision, remarks });
    onDone();
  });
  return (
    // Enter inside the reason box approves, exactly like the first submit button of the old form
    <form className="stack" onSubmit={(e) => { e.preventDefault(); decide('Approved'); }}>
      <input name="remarks" maxLength={255} placeholder="Reason if declining" value={remarks} onChange={(e) => setRemarks(e.target.value)} />
      <button className="btn small" type="submit">Approve</button>
      <button className="btn ghost small" type="button" onClick={() => decide('Declined')}>Decline</button>
    </form>
  );
}

export default function Requests({ query }) {
  usePageTitle('Requests');
  const status = STATUSES.includes(query.status) ? query.status : 'all';
  const state = useApi('requests', { status });
  const [pick, setPick] = useState(status);
  useEffect(() => setPick(status), [status]);
  const decideSubmit = useSubmit();
  const isAdmin = useSession().user?.level === 'admin';   // admins also see the College column

  return (
    <Async state={state}>
      {(d) => (
          <>
            <Head title="College requests" sub="Officers submit proposals; the assigned College Mayor reviews requests for their college.">
              {d.canSubmit ? <Link className="btn" to="/requests?view=new">New request</Link> : null}
            </Head>

            {query.view === 'new' && d.canSubmit
              ? <NewRequest defaultYear={d.defaultYear} onSaved={state.reload} />
              : null}

            <ErrorList errors={decideSubmit.errors} />

            <form className="search" onSubmit={(e) => { e.preventDefault(); navigate('/requests' + qs({ status: pick === 'all' ? '' : pick })); }}>
              <label className="inline">Status
                <select name="status" value={pick} onChange={(e) => setPick(e.target.value)}>
                  {['all', ...STATUSES].map((x) => <option key={x} value={x}>{x === 'all' ? 'All statuses' : x}</option>)}
                </select>
              </label>
              <button className="btn ghost">Filter</button>
            </form>

            <div className="card tablewrap"><table>
              <thead><tr>
                <th>Request</th>{isAdmin ? <th>College</th> : null}<th>Requested by</th><th>School year</th><th>Date</th>
                <th className="num">Amount</th><th>Status</th>{d.canDecide ? <th>Decision</th> : null}
              </tr></thead>
              <tbody>
                {d.rows.map((r) => (
                  <tr key={r.request_id}>
                    <td><strong>{r.event_title}</strong><br /><small className="muted">{r.request_type} · {r.purpose}</small></td>
                    {isAdmin ? <td>{r.college_name ?? 'Unassigned'}</td> : null}
                    <td>{`${r.first_name ?? ''} ${r.last_name ?? ''}`.trim() || r.email}</td>
                    <td>{r.school_year ?? '—'}</td>
                    <td>{r.event_date}</td>
                    <td className="num">{money(r.amount_requested)}</td>
                    <td>
                      <Pill kind={String(r.status).toLowerCase()}>{r.status}</Pill>
                      {r.admin_remarks ? <><br /><small className="muted">{r.admin_remarks}</small></> : null}
                    </td>
                    {d.canDecide ? (
                      <td>{r.status === 'Pending'
                        ? <DecideForm id={Number(r.request_id)} run={decideSubmit.run} onDone={state.reload} />
                        : '—'}</td>
                    ) : null}
                  </tr>
                ))}
                {!d.rows.length ? <Empty cols={isAdmin ? 8 : 7}>No requests found.</Empty> : null}
              </tbody>
            </table></div>
          </>
      )}
    </Async>
  );
}
