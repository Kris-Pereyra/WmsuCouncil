import { Link, navigate, qs } from '../router.jsx';
import { useApi, useForm, usePageTitle } from '../hooks.js';
import { Async, Empty, Pill, money } from '../components/ui.jsx';

function Filters({ filters }) {
  const { values, bind } = useForm({ from: filters.from || '', to: filters.to || '', kind: filters.kind || 'all' });
  const submit = (e) => { e.preventDefault(); navigate('/ledger' + qs({ ...values, kind: values.kind === 'all' ? '' : values.kind })); };
  return (
    <form className="search" onSubmit={submit}>
      <label className="inline">From <input type="date" {...bind('from')} /></label>
      <label className="inline">To <input type="date" {...bind('to')} /></label>
      <label className="inline">Show
        <select {...bind('kind')}>
          <option value="all">All</option>
          <option value="Collection">Collections only</option>
          <option value="Expense">Expenses only</option>
        </select>
      </label>
      <button className="btn" type="submit">Filter</button>
      <Link className="btn ghost" to="/ledger">Reset</Link>
    </form>
  );
}

export default function Ledger({ query }) {
  usePageTitle('Ledger');
  const state = useApi('ledger', { from: query.from, to: query.to, kind: query.kind });
  return (
    <Async state={state}>
      {(data) => {
        const net = data.in - data.out;
        return (
          <>
            <div className="head"><h1>Ledger</h1></div>
            <Filters key={JSON.stringify(data.filters)} filters={data.filters} />

            <section className="cards">
              <div className="stat in"><span>Money in</span><strong>{money(data.in)}</strong></div>
              <div className="stat out"><span>Money out</span><strong>{money(data.out)}</strong></div>
              <div className={`stat bal ${net < 0 ? 'neg' : ''}`}><span>Net for this period</span><strong>{money(net)}</strong></div>
            </section>

            <div className="card tablewrap"><table>
              <thead><tr><th>Date</th><th>Type</th><th>Receipt / Voucher</th><th>Details</th><th className="num">In</th><th className="num">Out</th></tr></thead>
              <tbody>
                {data.rows.map((r, i) => (
                  <tr key={i}>
                    <td className="nw">{r.d}</td>
                    <td><Pill kind={r.kind === 'Collection' ? 'approved' : 'draft'}>{r.kind}</Pill></td>
                    <td className="nw">{r.ref}</td>
                    <td>{r.detail}</td>
                    <td className="num pos">{Number(r.inflow) > 0 ? money(r.inflow) : ''}</td>
                    <td className="num neg">{Number(r.outflow) > 0 ? money(r.outflow) : ''}</td>
                  </tr>
                ))}
                {!data.rows.length && <Empty cols={6}>No transactions match these filters.</Empty>}
              </tbody>
            </table></div>
          </>
        );
      }}
    </Async>
  );
}
