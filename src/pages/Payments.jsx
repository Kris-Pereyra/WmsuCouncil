import { useState } from 'react';
import { API_URL, api } from '../api.js';
import { useApi, usePageTitle, useSubmit } from '../hooks.js';
import { navigate, qs } from '../router.jsx';
import { Async, Empty, ErrorList, Pill, money } from '../components/ui.jsx';

const statusKind = (s) => String(s).toLowerCase().replace(/ /g, '-');

function PaymentRow({ r, isStudent, isVerifier, onUpload, onVerify, busy }) {
  const pid = Number(r.payment_id);
  const canUpload = isStudent && ['Unpaid', 'Rejected', 'Proof Submitted'].includes(r.status);
  const canVerify = !canUpload && isVerifier && ['Proof Submitted', 'Under Verification'].includes(r.status);
  return (
    <tr>
      {!isStudent && (
        <>
          <td>{r.last_name + ', ' + r.first_name}<br /><small className="muted">{r.student_no}</small></td>
          <td>{r.college_name}</td>
        </>
      )}
      <td>{r.fee_name}</td>
      <td>{r.school_year}</td>
      <td className="num">{money(r.amount_due)}</td>
      <td>
        <Pill kind={statusKind(r.status)}>{r.status}</Pill>
        {r.status === 'Verified' && (
          <><br /><small className="muted">College {money(r.college_share)} · University {money(r.university_share)}</small></>
        )}
      </td>
      <td>
        {r.proof_id
          ? <a href={`${API_URL}?r=payment-proof&id=${Number(r.proof_id)}`}>View latest proof</a>
          : <span className="muted">No proof</span>}
        {canUpload && (
          <form className="stack" encType="multipart/form-data" onSubmit={(e) => onUpload(e, pid)}>
            <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" required />
            <button className="btn small" disabled={busy}>Upload proof</button>
          </form>
        )}
        {canVerify && (
          <form className="stack" onSubmit={(e) => onVerify(e, pid)}>
            <input name="receipt_no" maxLength={60} placeholder="Physical receipt no." />
            <input name="remarks" maxLength={255} placeholder="Reason if rejecting" />
            <button className="btn small" name="decision" value="Verified" disabled={busy}>Verify / paid</button>
            <button className="btn ghost small" name="decision" value="Under Verification" disabled={busy}>Check physical record</button>
            <button className="btn ghost small" name="decision" value="Rejected" disabled={busy}>Reject</button>
          </form>
        )}
      </td>
    </tr>
  );
}

export default function Payments({ query = {} }) {
  const filterSy = query.school_year || '';
  const state = useApi('payments', { school_year: filterSy });
  const { errors, busy, run } = useSubmit();
  const [sy, setSy] = useState(filterSy);
  usePageTitle('Payments');

  const upload = (e, paymentId) => {
    e.preventDefault();
    const form = e.currentTarget;
    const fd = new FormData(form);
    fd.append('do', 'upload');
    fd.append('payment_id', String(paymentId));
    run(async () => {
      await api.upload('payments', fd);
      form.reset();
      state.reload();
    });
  };

  const verify = (e, paymentId) => {
    e.preventDefault();
    const f = new FormData(e.currentTarget);
    const decision = e.nativeEvent.submitter?.value || '';
    run(async () => {
      await api.post('payments', {
        do: 'verify',
        payment_id: paymentId,
        decision,
        receipt_no: f.get('receipt_no') || '',
        remarks: f.get('remarks') || '',
      });
      state.reload();
    });
  };

  return (
    <Async state={state}>
      {({ rows, years, isStudent, isVerifier }) => (
        <>
          <div className="head">
            <div>
              <h1>{isStudent ? 'My annual fees' : 'Payment verification'}</h1>
              <p className="muted">Insurance ₱70 + CSC ₱200 = ₱270 per school year. Verified payments are split 70% to the college and 30% to the university.</p>
            </div>
          </div>
          <ErrorList errors={errors} />
          {!isStudent && (
            <form className="search" onSubmit={(e) => { e.preventDefault(); navigate('/payments' + qs({ school_year: sy })); }}>
              <label className="inline">School year
                <select name="school_year" value={sy} onChange={(e) => setSy(e.target.value)}>
                  <option value="">All years</option>
                  {years.map((y) => <option key={y} value={y}>{y}</option>)}
                </select>
              </label>
              <button className="btn ghost">Filter</button>
            </form>
          )}
          <div className="card tablewrap">
            <table>
              <thead>
                <tr>
                  {!isStudent && (<><th>Student</th><th>College</th></>)}
                  <th>Fee</th><th>School year</th><th className="num">Amount</th><th>Status</th><th>Proof / action</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <PaymentRow key={r.payment_id} r={r} isStudent={isStudent} isVerifier={isVerifier}
                    onUpload={upload} onVerify={verify} busy={busy} />
                ))}
                {!rows.length && <Empty cols={isStudent ? 6 : 8}>No payment records found for this school year.</Empty>}
              </tbody>
            </table>
          </div>
          <section className="card">
            <h2>How the ₱270 is distributed</h2>
            <div className="tablewrap">
              <table>
                <thead><tr><th>Fee</th><th className="num">Gross</th><th className="num">College 70%</th><th className="num">University 30%</th></tr></thead>
                <tbody>
                  <tr><td>Insurance</td><td className="num">₱70.00</td><td className="num">₱49.00</td><td className="num">₱21.00</td></tr>
                  <tr><td>CSC</td><td className="num">₱200.00</td><td className="num">₱140.00</td><td className="num">₱60.00</td></tr>
                  <tr><th>Total</th><th className="num">₱270.00</th><th className="num">₱189.00</th><th className="num">₱81.00</th></tr>
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}
    </Async>
  );
}
