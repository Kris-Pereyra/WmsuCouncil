import { Link, navigate, qs } from '../router.jsx';
import { useApi, useForm, usePageTitle } from '../hooks.js';
import { Async, Empty, money } from '../components/ui.jsx';

// PHP treats "0", "" and 0 as false; the old page printed an amount only when it was "truthy".
const truthy = (v) => v !== null && v !== undefined && v !== '' && v !== '0' && v !== 0 && v !== false;

function Filters({ data }) {
  const { values, bind } = useForm({
    college_id: data.filters.college_id || 0,
    school_year: data.filters.school_year || '',
    from: data.filters.from || '',
    to: data.filters.to || '',
  });
  const submit = (e) => { e.preventDefault(); navigate('/college-ledger' + qs(values)); };
  return (
    <form className="search" onSubmit={submit}>
      {data.isAdmin && (
        <label className="inline">College
          <select {...bind('college_id')}>
            <option value="0">All colleges</option>
            {data.colleges.map((c) => <option key={c.college_id} value={c.college_id}>{c.college_name}</option>)}
          </select>
        </label>
      )}
      <label className="inline">School year
        <select {...bind('school_year')}>
          <option value="">All years</option>
          {data.years.map((y) => <option key={y} value={y}>{y}</option>)}
        </select>
      </label>
      <label className="inline">From<input type="date" {...bind('from')} /></label>
      <label className="inline">To<input type="date" {...bind('to')} /></label>
      <button className="btn">Filter</button>
      <Link className="btn ghost" to="/college-ledger">Reset</Link>
    </form>
  );
}

export default function CollegeLedger({ query }) {
  usePageTitle('College history');
  const state = useApi('college_ledger', {
    college_id: query.college_id, from: query.from, to: query.to, school_year: query.school_year,
  });
  return (
    <Async state={state}>
      {(data) => (
        <>
          <div className="head"><div>
            <h1>{data.isAdmin ? 'University financial history' : 'College financial history'}</h1>
            <p className="muted">Filter by college, date range, and school year. Historical entries are retained.</p>
          </div></div>

          {/* key: the filter inputs restart from the server's cleaned values after every search */}
          <Filters key={JSON.stringify(data.filters)} data={data} />

          <section className="cards">
            <div className="stat in"><span>College inflow</span><strong>{money(data.in)}</strong></div>
            <div className="stat"><span>University 30% share</span><strong>{money(data.university)}</strong></div>
            <div className="stat out"><span>Expenses</span><strong>{money(data.out)}</strong></div>
            <div className="stat bal"><span>College net</span><strong>{money(data.in - data.out)}</strong></div>
          </section>

          <div className="card tablewrap"><table>
            <thead><tr>
              <th>Date</th><th>College</th><th>School year</th><th>Type</th><th>Reference</th><th>Details</th>
              <th className="num">College in</th><th className="num">University share</th><th className="num">Out</th>
            </tr></thead>
            <tbody>
              {data.rows.map((r, i) => (
                <tr key={i}>
                  <td>{r.tx_date}</td>
                  <td>{r.college_name}</td>
                  <td>{r.school_year ?? '—'}</td>
                  <td>{r.kind}</td>
                  <td>{r.reference_no}</td>
                  <td>{r.detail}</td>
                  <td className="num pos">{truthy(r.inflow) ? money(r.inflow) : ''}</td>
                  <td className="num">{truthy(r.university_share) ? money(r.university_share) : '—'}</td>
                  <td className="num neg">{truthy(r.outflow) ? money(r.outflow) : ''}</td>
                </tr>
              ))}
              {!data.rows.length && <Empty cols={9}>No transactions match these filters.</Empty>}
            </tbody>
          </table></div>
        </>
      )}
    </Async>
  );
}
