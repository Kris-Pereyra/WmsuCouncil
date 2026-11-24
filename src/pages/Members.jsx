import { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { navigate, qs } from '../router.jsx';
import { Async, Empty, ErrorList, Head, Pill } from '../components/ui.jsx';

/** Mayor only: pick a student of the college and give them a council position. */
function AssignOfficer({ options, today, onSaved, run, busy }) {
  const { values, set, bind } = useForm({ student_id: '', position: '', term_start: today });
  const submit = (e) => {
    e.preventDefault();
    run(async () => {
      await api.post('members', { do: 'assign_officer', ...values });
      set('student_id', '');
      set('position', '');
      onSaved();
    });
  };
  return (
    <section className="card"><h2>Assign an officer</h2>
      <form className="search" onSubmit={submit}>
        <select {...bind('student_id')} required>
          <option value="">Choose a student from your college</option>
          {options.map((o) => <option key={o.id} value={o.id}>{o.label}</option>)}
        </select>
        <input {...bind('position')} maxLength={40} required placeholder="Position (e.g. Treasurer)" />
        <input type="date" {...bind('term_start')} required />
        <button className="btn" disabled={busy}>Assign officer</button>
      </form>
    </section>
  );
}

export default function Members({ query }) {
  usePageTitle('Members');
  const q = query.q || '';
  const state = useApi('members', { q });
  const [search, setSearch] = useState(q);
  useEffect(() => setSearch(q), [q]);
  const { errors, busy, run } = useSubmit();

  const endTerm = (officerId) => run(async () => {
    await api.post('members', { do: 'end_officer', officer_id: officerId });
    state.reload();
  });

  return (
    <Async state={state}>
      {(d) => (
        <>
          <Head
            title={d.isAdmin ? 'University student directory' : 'College members'}
            count={d.rows.length}
            sub={d.isAdmin
              ? 'View student and officer records across WMSU. Assignments of ordinary officers are managed by each College Mayor.'
              : 'Manage officer positions only within your assigned college.'} />

          <ErrorList errors={errors} />

          {!d.isAdmin
            ? <AssignOfficer options={d.studentOptions} today={d.today} onSaved={state.reload} run={run} busy={busy} />
            : null}

          <form className="search" onSubmit={(e) => { e.preventDefault(); navigate('/members' + qs({ q: search.trim() })); }}>
            <input type="search" name="q" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search student, program or position" />
            <button className="btn ghost">Search</button>
          </form>

          <div className="card tablewrap"><table>
            <thead><tr><th>Student</th><th>College / program</th><th>Account</th><th>Current position</th>{!d.isAdmin ? <th>Action</th> : null}</tr></thead>
            <tbody>
              {d.rows.map((s) => (
                <tr key={s.student_id}>
                  <td><strong>{s.name}</strong><br /><small className="muted">{s.student_no}</small></td>
                  <td>{s.college}<br /><small className="muted">{s.program} · Year {s.year_level}</small></td>
                  <td>{s.has_account ? <Pill kind="approved">Registered</Pill> : <span className="muted">No account</span>}</td>
                  <td>
                    {s.positions.map((o) => (
                      <span key={o.officer_id}><Pill kind="officer">{o.position}</Pill> <small className="muted">since {o.term_start}</small></span>
                    ))}
                    {!s.positions.length ? <span className="muted">—</span> : null}
                  </td>
                  {!d.isAdmin ? (
                    <td>{s.positions.map((o) => (
                      <form key={o.officer_id} onSubmit={(e) => { e.preventDefault(); endTerm(Number(o.officer_id)); }}>
                        <button className="btn ghost small">End {o.position}</button>
                      </form>
                    ))}</td>
                  ) : null}
                </tr>
              ))}
              {!d.rows.length ? <Empty cols={d.isAdmin ? 4 : 5}>No students found.</Empty> : null}
            </tbody>
          </table></div>
        </>
      )}
    </Async>
  );
}
