import { Link, navigate, qs } from '../router.jsx';
import { api } from '../api.js';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { Async, Empty, ErrorList, Pill, money } from '../components/ui.jsx';

function RecordForm({ today, sub, onSaved }) {
  const blank = { entry_date: today, school_year: '', category: '', reference_no: '', description: '', amount: '' };
  const { values, setValues, bind } = useForm(blank);
  const submit = async (e) => {
    e.preventDefault();
    const res = await sub.run(() => api.post('university_ledger', values));
    if (res) { setValues(blank); onSaved(); }          // the old page redirected back to a clean form
  };
  return (
    <section className="card"><h2>Record central-fund expense</h2>
      <form className="stack" onSubmit={submit}>
        <div className="two">
          <label>Date<input type="date" {...bind('entry_date')} required /></label>
          <label>School year<input {...bind('school_year')} maxLength={9} placeholder="2026-2027" required /></label>
        </div>
        <div className="two">
          <label>Category<input {...bind('category')} maxLength={80} placeholder="University program / operations" required /></label>
          <label>Reference no. (optional)<input {...bind('reference_no')} maxLength={80} /></label>
        </div>
        <label>Description<input {...bind('description')} maxLength={255} required /></label>
        <label>Amount (₱)<input type="number" {...bind('amount')} min="0.01" step="0.01" required /></label>
        <button className="btn" disabled={sub.busy}>Record outflow</button>
      </form>
    </section>
  );
}

function Filters({ data }) {
  const { values, bind } = useForm({
    school_year: data.filters.school_year || '', from: data.filters.from || '', to: data.filters.to || '',
  });
  const submit = (e) => { e.preventDefault(); navigate('/university-ledger' + qs(values)); };
  return (
    <form className="search" onSubmit={submit}>
      <label className="inline">School year
        <select {...bind('school_year')}>
          <option value="">All years</option>
          {data.years.map((y) => <option key={y} value={y}>{y}</option>)}
        </select>
      </label>
      <label className="inline">From<input type="date" {...bind('from')} /></label>
      <label className="inline">To<input type="date" {...bind('to')} /></label>
      <button className="btn ghost">Filter</button>
      <Link className="btn ghost" to="/university-ledger">Reset</Link>
    </form>
  );
}

export default function UniversityLedger({ query }) {
  usePageTitle('University fund');
  const state = useApi('university_ledger', { from: query.from, to: query.to, school_year: query.school_year });
  const sub = useSubmit();
  return (
    <Async state={state}>
      {(data) => (
        <>
          <div className="head"><div>
            <h1>University central fund</h1>
            <p className="muted">The university’s 30% share of verified student fees is posted here automatically. Only Admin can record central-fund outflows.</p>
          </div></div>
          <ErrorList errors={sub.errors} />

          <section className="cards">
            <div className="stat in"><span>Central fund inflow</span><strong>{money(data.in)}</strong></div>
            <div className="stat out"><span>Central fund outflow</span><strong>{money(data.out)}</strong></div>
            <div className="stat bal"><span>Net in selected period</span><strong>{money(data.in - data.out)}</strong></div>
          </section>

          <RecordForm today={data.today} sub={sub} onSaved={state.reload} />
          <Filters key={JSON.stringify(data.filters)} data={data} />

          <div className="card tablewrap"><table>
            <thead><tr>
              <th>Date</th><th>School year</th><th>Direction</th><th>Category</th><th>Reference</th><th>Description</th><th className="num">Amount</th>
            </tr></thead>
            <tbody>
              {data.rows.map((r) => (
                <tr key={r.entry_id}>
                  <td>{r.entry_date}</td>
                  <td>{r.school_year}</td>
                  <td><Pill kind={r.direction === 'In' ? 'approved' : 'closed'}>{r.direction}</Pill></td>
                  <td>{r.category}</td>
                  <td>{r.reference_no ?? '—'}</td>
                  <td>{r.description}</td>
                  <td className="num">{money(r.amount)}</td>
                </tr>
              ))}
              {!data.rows.length && <Empty cols={7}>No central-fund transactions match these filters.</Empty>}
            </tbody>
          </table></div>
        </>
      )}
    </Async>
  );
}
