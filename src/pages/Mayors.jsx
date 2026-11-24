import { api } from '../api.js';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { Async, Empty, ErrorList, Head, Pill } from '../components/ui.jsx';

const isOn = (v) => Number(v) === 1;   // MySQL may send tinyint as "1"/"0"

function AssignMayor({ colleges, today, onSaved, run, busy }) {
  const { values, set, bind } = useForm({ college_id: '', email: '', start_date: today });
  const submit = (e) => {
    e.preventDefault();
    run(async () => {
      await api.post('mayors', { do: 'assign', ...values });
      set('college_id', '');
      set('email', '');
      onSaved();
    });
  };
  return (
    <section className="card"><h2>Assign or reassign a Mayor</h2>
      <form className="stack" onSubmit={submit}>
        <label>College
          <select {...bind('college_id')} required>
            <option value="">Choose college</option>
            {colleges.map((c) => <option key={c.college_id} value={c.college_id}>{c.college_name}</option>)}
          </select>
        </label>
        <label>Mayor’s WMSU account email<input type="email" {...bind('email')} required placeholder="student@wmsu.edu.ph" /></label>
        <label>Start date<input type="date" {...bind('start_date')} required /></label>
        <button className="btn" disabled={busy}>Save Mayor assignment</button>
      </form>
    </section>
  );
}

export default function Mayors() {
  usePageTitle('Mayor assignments');
  const state = useApi('mayors');
  const { errors, busy, run } = useSubmit();

  const endAssignment = (id) => run(async () => {
    await api.post('mayors', { do: 'remove', assignment_id: id });
    state.reload();
  });

  return (
    <Async state={state}>
      {(d) => (
        <>
          <Head title="College Mayor assignments" sub="The university administrator controls only Mayor assignments. Each Mayor must be a registered student of the assigned college." />
          <ErrorList errors={errors} />
          <AssignMayor colleges={d.colleges} today={d.today} onSaved={state.reload} run={run} busy={busy} />

          <section className="card"><h2>Assignment history</h2><div className="tablewrap"><table>
            <thead><tr><th>College</th><th>Mayor</th><th>Start</th><th>End</th><th>Status</th><th></th></tr></thead>
            <tbody>
              {d.rows.map((r) => (
                <tr key={r.assignment_id}>
                  <td>{r.college_name}</td>
                  <td>{`${r.first_name ?? ''} ${r.last_name ?? ''}`.trim() || r.email}<br /><small className="muted">{r.email}</small></td>
                  <td>{r.start_date}</td>
                  <td>{r.end_date ?? '—'}</td>
                  <td>{isOn(r.is_active) ? <Pill kind="approved">Active</Pill> : <Pill kind="closed">Ended</Pill>}</td>
                  <td>{isOn(r.is_active) ? (
                    <form onSubmit={(e) => { e.preventDefault(); endAssignment(Number(r.assignment_id)); }}>
                      <button className="btn ghost small">End assignment</button>
                    </form>
                  ) : null}</td>
                </tr>
              ))}
              {!d.rows.length ? <Empty cols={6}>No Mayor assignments yet.</Empty> : null}
            </tbody>
          </table></div></section>
        </>
      )}
    </Async>
  );
}
