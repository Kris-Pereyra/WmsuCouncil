import { useApi, usePageTitle } from '../hooks.js';
import { useSession } from '../session.jsx';
import { Link } from '../router.jsx';
import { Async, Empty, Multiline, Pill, money } from '../components/ui.jsx';

function StudentView({ d }) {
  const { student, payments, news } = d;
  return (
    <>
      <div className="two">
        <section className="card">
          <h2>Enrollment information</h2>
          <dl className="detail">
            <dt>College</dt><dd>{student.college}</dd>
            <dt>Program</dt><dd>{student.program}</dd>
            <dt>Year level</dt><dd>Year {parseInt(student.year_level, 10) || 0}</dd>
            <dt>School year enrolled</dt><dd>{student.school_year ?? 'Not set'}</dd>
          </dl>
        </section>
        <section className="card">
          <h2>Annual fees</h2>
          <p className="muted">Insurance ₱70 + CSC ₱200 = ₱270.</p>
          <div className="tablewrap">
            <table>
              <thead><tr><th>Fee</th><th>School year</th><th>Amount</th><th>Status</th></tr></thead>
              <tbody>
                {payments.map((p, i) => (
                  <tr key={i}><td>{p.fee_name}</td><td>{p.school_year}</td><td>{money(p.amount_due)}</td><td>{p.status}</td></tr>
                ))}
                {!payments.length && <Empty cols={4}>No fee records yet. Open My payments to check available fees.</Empty>}
              </tbody>
            </table>
          </div>
          <p><Link to="/payments">Open payment history and upload proof</Link></p>
        </section>
      </div>
      <section className="card">
        <h2>Latest announcements</h2>
        {news.map((n, i) => (
          <div key={i}>
            <article>
              <Pill>{n.announcement_type}</Pill><strong> {n.title}</strong>
              <p><Multiline text={n.body} /></p>
              <small className="muted">Published {n.publish_at} {n.event_date ? '· Event ' + n.event_date : ''}</small>
            </article>
            <hr />
          </div>
        ))}
        {!news.length && <p className="muted">No announcements published yet.</p>}
        <Link to="/announcements">View all announcements</Link>
      </section>
    </>
  );
}

function StaffView({ d, user }) {
  const { stats } = d;
  const level = user.level;
  return (
    <>
      <section className="cards">
        <div className="stat"><span>Students</span><strong>{Number(stats.students).toLocaleString('en-US')}</strong></div>
        <div className="stat"><span>Requests pending</span><strong>{stats.pending}</strong></div>
        <div className="stat"><span>Payments needing review</span><strong>{stats.waiting}</strong></div>
      </section>
      <div className="two">
        <section className="card">
          <h2>Quick actions</h2>
          <ul className="plain">
            <li><Link to="/requests">Review or submit requests</Link></li>
            <li><Link to="/payments">Verify student payment proofs</Link></li>
            <li><Link to="/announcements">Publish announcements</Link></li>
            {(level === 'admin' || level === 'mayor') && (
              <>
                <li><Link to="/college-ledger">View financial history</Link></li>
                <li><Link to="/members">Manage members</Link></li>
              </>
            )}
            {level === 'admin' && (
              <>
                <li><Link to="/mayors">Assign College Mayors</Link></li>
                <li><Link to="/enrollments">Manage school-year enrollments</Link></li>
                <li><Link to="/catalog">Manage colleges and programs</Link></li>
                <li><Link to="/university-ledger">Manage university central fund</Link></li>
              </>
            )}
          </ul>
        </section>
        <section className="card">
          <h2>Access scope</h2>
          {level === 'admin' && <p>You have university-wide access across all colleges. You can assign and reassign College Mayors.</p>}
          {level === 'mayor' && <p>You can manage council activity for <strong>{user.college}</strong>. Officer assignments and request approvals are limited to this college.</p>}
          {level === 'officer' && <p>You can verify payments, publish announcements, view your college requests, and submit proposals for Mayor approval. You cannot allocate funds directly.</p>}
        </section>
      </div>
    </>
  );
}

export default function Dashboard() {
  usePageTitle('Dashboard');
  const { user } = useSession();
  const state = useApi('dashboard');
  return (
    <Async state={state}>
      {(d) => (
        <>
          <div className="head">
            <div>
              <h1>{d.greeting}, {d.first}</h1>
              <p className="muted lead-sm">
                {user.level === 'admin' && 'University-wide WMSU Student Council Funds overview.'}
                {user.level === 'mayor' && `College Mayor workspace for ${user.college}.`}
                {user.level === 'officer' && `Officer workspace for ${user.college ?? 'your college'}.`}
                {user.level === 'student' && 'Your student account, annual fees, and council announcements.'}
              </p>
            </div>
          </div>
          {user.level === 'student' ? <StudentView d={d} /> : <StaffView d={d} user={user} />}
        </>
      )}
    </Async>
  );
}
