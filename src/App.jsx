import { useEffect } from 'react';
import { matchPath, navigate, useRoute, Link } from './router.jsx';
import { useSession } from './session.jsx';
import { useBodyClass } from './hooks.js';
import { AppShell, PublicLayout } from './components/Layout.jsx';

import Landing from './pages/Landing.jsx';
import Login from './pages/Login.jsx';
import Signup from './pages/Signup.jsx';
import Verify from './pages/Verify.jsx';
import Dashboard from './pages/Dashboard.jsx';
import Profile from './pages/Profile.jsx';
import Announcements from './pages/Announcements.jsx';
import Payments from './pages/Payments.jsx';
import Requests from './pages/Requests.jsx';
import Members from './pages/Members.jsx';
import Mayors from './pages/Mayors.jsx';
import Enrollments from './pages/Enrollments.jsx';
import Catalog from './pages/Catalog.jsx';
import CollegeLedger from './pages/CollegeLedger.jsx';
import UniversityLedger from './pages/UniversityLedger.jsx';
import Ledger from './pages/Ledger.jsx';
import Audit from './pages/Audit.jsx';
import Crud from './pages/Crud.jsx';

/**
 * One row per screen. `layout`: 'public' (top bar + footer) or 'app' (sidebar, needs a login).
 * `guest`: only for logged-out visitors (logged-in users are sent to the dashboard).
 * Each old *.php page became one hash route: payments.php -> #/payments, crud.php?t=budget -> #/crud/budget, ...
 */
const ROUTES = [
  { path: '/', page: Landing, layout: 'public' },
  { path: '/login', page: Login, layout: 'public', guest: true },
  { path: '/signup', page: Signup, layout: 'public', guest: true },
  { path: '/verify', page: Verify, layout: 'public' },

  { path: '/dashboard', page: Dashboard, layout: 'app' },
  { path: '/profile', page: Profile, layout: 'app' },
  { path: '/announcements', page: Announcements, layout: 'app' },
  { path: '/payments', page: Payments, layout: 'app' },
  { path: '/requests', page: Requests, layout: 'app' },
  { path: '/members', page: Members, layout: 'app' },
  { path: '/mayors', page: Mayors, layout: 'app' },
  { path: '/enrollments', page: Enrollments, layout: 'app' },
  { path: '/catalog', page: Catalog, layout: 'app' },
  { path: '/college-ledger', page: CollegeLedger, layout: 'app' },
  { path: '/university-ledger', page: UniversityLedger, layout: 'app' },
  { path: '/ledger', page: Ledger, layout: 'app' },
  { path: '/audit', page: Audit, layout: 'app' },
  { path: '/crud/:t/new', page: Crud, layout: 'app' },
  { path: '/crud/:t/:id/edit', page: Crud, layout: 'app' },
  { path: '/crud/:t', page: Crud, layout: 'app' },
];

function find(path) {
  for (const r of ROUTES) {
    const params = matchPath(r.path, path);
    if (params) return { ...r, params };
  }
  return null;
}

function Notice({ title, children }) {
  return <div className="wrap"><div className="card authcard narrow" style={{ margin: '40px auto' }}><h1>{title}</h1>{children}</div></div>;
}

export default function App() {
  const route = useRoute();
  const { loading, user, dbError } = useSession();
  const hit = find(route.path);

  const needLogin = hit && hit.layout === 'app' && !user;
  const guestOnly = hit && hit.guest && user;
  useBodyClass(hit?.layout === 'app' ? '' : 'pub');

  useEffect(() => {
    if (loading) return;
    if (needLogin) navigate('/login', { replace: true });
    else if (guestOnly) navigate('/dashboard', { replace: true });
  }, [loading, needLogin, guestOnly]);

  if (loading) return <p className="muted" style={{ margin: '40px auto' }}>Loading…</p>;

  if (!hit) {
    const Wrap = user ? AppShell : PublicLayout;
    return <Wrap><Notice title="Page not found"><p>That page does not exist.</p><p><Link className="btn" to={user ? '/dashboard' : '/'}>Go back</Link></p></Notice></Wrap>;
  }
  if (needLogin || guestOnly) return null;

  const Layout = hit.layout === 'app' ? AppShell : PublicLayout;
  if (dbError && hit.path !== '/') {
    return (
      <PublicLayout>
        <Notice title="Cannot reach the database">
          <p>{dbError}</p>
          <p className="muted small">Check <code>config.php</code>, make sure MySQL/MariaDB is running, then run <a href="../setup.php">setup.php</a>.</p>
        </Notice>
      </PublicLayout>
    );
  }
  const Page = hit.page;
  // key = path: moving between two screens that share a component (e.g. two crud tables) starts it fresh
  return <Layout><Page key={route.path} params={hit.params} query={route.query} /></Layout>;
}
