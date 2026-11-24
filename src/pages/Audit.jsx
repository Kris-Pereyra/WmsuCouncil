import { navigate, qs } from '../router.jsx';
import { useApi, useForm, usePageTitle } from '../hooks.js';
import { Async, Empty } from '../components/ui.jsx';

function Filters({ filters }) {
  const { values, bind } = useForm({ from: filters.from || '', to: filters.to || '' });
  const submit = (e) => { e.preventDefault(); navigate('/audit' + qs(values)); };
  return (
    <form className="search" onSubmit={submit}>
      <label className="inline">From<input type="date" {...bind('from')} /></label>
      <label className="inline">To<input type="date" {...bind('to')} /></label>
      <button className="btn ghost">Filter</button>
    </form>
  );
}

export default function Audit({ query }) {
  usePageTitle('Audit trail');
  const state = useApi('audit', { from: query.from, to: query.to });
  return (
    <Async state={state}>
      {(data) => (
        <>
          <div className="head"><div>
            <h1>Audit trail</h1>
            <p className="muted">Recent recorded actions. Mayor view is restricted to the assigned college.</p>
          </div></div>
          <Filters key={JSON.stringify(data.filters)} filters={data.filters} />
          <div className="card tablewrap"><table>
            <thead><tr><th>Date / time</th><th>College</th><th>Account</th><th>Action</th><th>Record</th><th>Details</th></tr></thead>
            <tbody>
              {data.rows.map((r) => (
                <tr key={r.audit_id ?? `${r.created_at}-${r.action}-${r.entity_id}`}>
                  <td className="nw">{r.created_at}</td>
                  <td>{r.college_name ?? 'University-wide'}</td>
                  <td>{r.email ?? 'System'}</td>
                  <td>{r.action}</td>
                  <td>{r.entity_type} #{r.entity_id ?? '—'}</td>
                  <td><small>{r.new_values ?? r.old_values ?? r.ip_address ?? '—'}</small></td>
                </tr>
              ))}
              {!data.rows.length && <Empty cols={6}>No audit entries found.</Empty>}
            </tbody>
          </table></div>
        </>
      )}
    </Async>
  );
}
