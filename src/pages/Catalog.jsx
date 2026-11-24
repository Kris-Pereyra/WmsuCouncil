import { api } from '../api.js';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { Async, ErrorList, Head, Pill } from '../components/ui.jsx';

const isOn = (v) => Number(v) === 1;   // MySQL may send tinyint as "1"/"0"

function AddCollege({ run, busy, onSaved }) {
  const { values, setValues, bind } = useForm({ college_name: '', college_code: '' });
  return (
    <section className="card"><h2>Add a college</h2>
      <form className="stack" onSubmit={(e) => {
        e.preventDefault();
        run(async () => { await api.post('catalog', { do: 'add_college', ...values }); setValues({ college_name: '', college_code: '' }); onSaved(); });
      }}>
        <label>Official college name<input {...bind('college_name')} maxLength={150} required /></label>
        <label>College code (optional)<input {...bind('college_code')} maxLength={30} placeholder="e.g. CCS" /></label>
        <button className="btn" disabled={busy}>Add college</button>
      </form>
    </section>
  );
}

function AddProgram({ colleges, run, busy, onSaved }) {
  const { values, setValues, bind } = useForm({ college_id: '', program_name: '' });
  return (
    <section className="card"><h2>Add a program</h2>
      <form className="stack" onSubmit={(e) => {
        e.preventDefault();
        run(async () => { await api.post('catalog', { do: 'add_program', ...values }); setValues({ college_id: '', program_name: '' }); onSaved(); });
      }}>
        <label>College
          <select {...bind('college_id')} required>
            <option value="">Choose college</option>
            {colleges.filter((c) => isOn(c.is_active)).map((c) => <option key={c.college_id} value={c.college_id}>{c.college_name}</option>)}
          </select>
        </label>
        <label>Program name<input {...bind('program_name')} maxLength={80} required /></label>
        <button className="btn" disabled={busy}>Add program</button>
      </form>
    </section>
  );
}

export default function Catalog() {
  usePageTitle('College/program catalog');
  const state = useApi('catalog');
  const { errors, busy, run } = useSubmit();

  const toggle = (what, idKey, id) => run(async () => {
    await api.post('catalog', { do: `toggle_${what}`, [idKey]: id });
    state.reload();
  });

  return (
    <Async state={state}>
      {(d) => (
        <>
          <Head title="College and program catalog" sub="Admin maintains the active college and program options used by student registration and enrollment records. Confirm names against the latest WMSU Registrar prospectus." />
          <ErrorList errors={errors} />
          <div className="two">
            <AddCollege run={run} busy={busy} onSaved={state.reload} />
            <AddProgram colleges={d.colleges} run={run} busy={busy} onSaved={state.reload} />
          </div>

          <section className="card"><h2>Colleges</h2><div className="tablewrap"><table>
            <thead><tr><th>College</th><th>Code</th><th>Programs</th><th>Status</th><th></th></tr></thead>
            <tbody>
              {d.colleges.map((c) => (
                <tr key={c.college_id}>
                  <td>{c.college_name}</td>
                  <td>{c.college_code ?? '—'}</td>
                  <td>{Number(c.program_count)}</td>
                  <td>{isOn(c.is_active) ? <Pill kind="approved">Active</Pill> : <Pill kind="closed">Inactive</Pill>}</td>
                  <td>
                    <form onSubmit={(e) => { e.preventDefault(); toggle('college', 'college_id', Number(c.college_id)); }}>
                      <button className="btn ghost small">{isOn(c.is_active) ? 'Deactivate' : 'Reactivate'}</button>
                    </form>
                  </td>
                </tr>
              ))}
            </tbody>
          </table></div></section>

          <section className="card"><h2>Programs</h2><div className="tablewrap"><table>
            <thead><tr><th>College</th><th>Program</th><th>Status</th><th></th></tr></thead>
            <tbody>
              {d.programs.map((pr) => (
                <tr key={pr.program_id}>
                  <td>{pr.college_name}</td>
                  <td>{pr.program_name}</td>
                  <td>{isOn(pr.is_active) ? <Pill kind="approved">Active</Pill> : <Pill kind="closed">Inactive</Pill>}</td>
                  <td>
                    <form onSubmit={(e) => { e.preventDefault(); toggle('program', 'program_id', Number(pr.program_id)); }}>
                      <button className="btn ghost small">{isOn(pr.is_active) ? 'Deactivate' : 'Reactivate'}</button>
                    </form>
                  </td>
                </tr>
              ))}
            </tbody>
          </table></div></section>
        </>
      )}
    </Async>
  );
}
