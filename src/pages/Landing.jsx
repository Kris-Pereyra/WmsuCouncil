import { Link } from '../router.jsx';
import { useSession } from '../session.jsx';
import { usePageTitle } from '../hooks.js';
import { Icon } from '../components/ui.jsx';

export default function Landing() {
  usePageTitle('Student council finance, made transparent');
  const { user, config } = useSession();
  const domain = config.allowedEmailDomain;

  return (
    <>
      <section className="hero"><div className="wrap hero-grid">
        <div className="hero-copy">
          <h1>Know where every peso of the student council goes.</h1>
          <p className="lead">Council Funds keeps collections, budgets, projects and expenses in one place. It is open to every verified student and managed by the officers you elected.</p>
          <div className="actions">
            {user ? (
              <Link className="btn" to="/dashboard">Open dashboard</Link>
            ) : (
              <>
                <Link className="btn" to="/signup">Create your account</Link>
                <Link className="btn ghost" to="/login">Log in</Link>
              </>
            )}
          </div>
          <ul className="hero-points">
            <li><Icon name="shield" />Verified school emails only</li>
            <li><Icon name="inbox" />College Mayor approval for funding</li>
          </ul>
        </div>

        <aside className="ledgercard" aria-label="Example of the dashboard">
          <div className="lc-top"><span>Fund balance</span><em>Example data</em></div>
          <strong className="lc-num">₱18,299.50</strong>
          <div className="split"><i style={{ width: '40%' }} /></div>
          <div className="lc-rows">
            <div><span>Collected</span><b className="pos">₱30,450.00</b></div>
            <div><span>Spent</span><b className="neg">₱12,150.50</b></div>
          </div>
          <div className="lc-req">
            <div><span className="pill pending">Pending</span><strong>Leadership Training</strong><small>₱25,000.00 requested</small></div>
            <div><span className="pill approved">Approved</span><strong>Intramurals</strong><small>₱60,000.00 requested</small></div>
          </div>
        </aside>
      </div></section>

      <section className="section" id="features"><div className="wrap">
        <h2 className="sec-title">Everything the council needs, nothing it doesn’t</h2>
        <div className="grid3">
          <article className="feat"><span className="ico"><Icon name="shield" /></span><h3>Verified students only</h3>
            <p>Sign up with your <code>{domain}</code> email and confirm it with a one-time code. No code, no account.</p></article>
          <article className="feat"><span className="ico"><Icon name="wallet" /></span><h3>Live fund balance</h3>
            <p>Total collected, total spent and what is left for each project, always calculated from the records and never typed in by hand.</p></article>
          <article className="feat"><span className="ico"><Icon name="inbox" /></span><h3>Funding requests</h3>
            <p>Officers request money for an event. Officers submit proposals to their College Mayor. Decisions and reasons are kept with the request.</p></article>
          <article className="feat"><span className="ico"><Icon name="book" /></span><h3>Full ledger</h3>
            <p>Every peso in and out on one page. Filter by date or type to prepare reports.</p></article>
          <article className="feat"><span className="ico"><Icon name="badge" /></span><h3>Real profiles</h3>
            <p>Profile picture, college, program, school year and contact number, plus the council position each student holds.</p></article>
          <article className="feat"><span className="ico"><Icon name="users" /></span><h3>Role-based access</h3>
            <p>Students, officers and the administrator each see and do only what their role allows.</p></article>
        </div>
      </div></section>

      <section className="section alt" id="how"><div className="wrap">
        <h2 className="sec-title">Get started in three steps</h2>
        <ol className="steps">
          <li><b>1</b><h3>Sign up</h3><p>Enter your name, program and school details, and your <code>{domain}</code> email.</p></li>
          <li><b>2</b><h3>Enter the code</h3><p>We email you a 6-digit code. Type it in to prove the email is yours.</p></li>
          <li><b>3</b><h3>Log in</h3><p>See the dashboard and your profile, including any council position you hold.</p></li>
        </ol>
      </div></section>

      <section className="section" id="roles"><div className="wrap">
        <h2 className="sec-title">Who can do what</h2>
        <div className="grid3">
          <article className="role"><h3>Student</h3><ul>
            <li>View their enrolled school year and announcements</li><li>See their own annual fees and payment history</li><li>Upload payment proof and update their own profile</li></ul></article>
          <article className="role"><h3>Council officer</h3><ul>
            <li>Verify payment proofs and publish college announcements</li><li>View college transaction history</li><li>Submit event, project and funding requests to the Mayor</li><li>Cannot approve their own fund allocation</li></ul></article>
          <article className="role"><h3>College Mayor</h3><ul><li>Manage only their assigned college</li><li>Approve or decline officer requests</li><li>Assign, reassign and end officer terms</li><li>Manage college budgets and projects</li></ul></article>
          <article className="role"><h3>Administrator</h3><ul><li>University-wide access across all colleges</li><li>Assign and reassign College Mayors</li><li>Manage the university central fund</li><li>Publish university-wide announcements</li></ul></article>
        </div>
      </div></section>

      <section className="cta"><div className="wrap">
        <h2>Ready to see where the money goes?</h2>
        {user
          ? <Link className="btn" to="/dashboard">Open dashboard</Link>
          : <Link className="btn" to="/signup">Create your account</Link>}
      </div></section>
    </>
  );
}
