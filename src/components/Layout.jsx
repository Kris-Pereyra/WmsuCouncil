import { useEffect } from 'react';
import { Link, navigate, useRoute } from '../router.jsx';
import { useSession } from '../session.jsx';
import { useBodyClass } from '../hooks.js';
import { Avatar, FlashBox, Icon, Pill } from './ui.jsx';

/** Scroll to a section of the landing page (hash URLs are taken by the router, so plain #anchors can't be used). */
export function goSection(id) {
  const scroll = () => document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
  if (parseHashPath() !== '/') {
    navigate('/');
    setTimeout(scroll, 60);
  } else {
    scroll();
  }
}
const parseHashPath = () => (window.location.hash.replace(/^#/, '').split('?')[0] || '/');

/* ------------------------------------------------------------------ public pages: landing, login, sign up, verify */
export function PublicLayout({ children }) {
  const { user, config } = useSession();
  useBodyClass('pub');
  const jump = (id) => (e) => { e.preventDefault(); goSection(id); };
  return (
    <>
      <header className="pubhead"><div className="wrap pubbar">
        <Link className="brand" to="/"><span className="logo">₱</span><span>Council Funds</span></Link>
        <nav className="pubnav" aria-label="Main">
          <a href="#/" onClick={jump('features')}>Features</a>
          <a href="#/" onClick={jump('how')}>How it works</a>
          <a href="#/" onClick={jump('roles')}>Roles</a>
          {user
            ? <Link className="btn" to="/dashboard">Open dashboard</Link>
            : <>
                <Link className="keep" to="/login">Log in</Link>
                <Link className="btn" to="/signup">Sign up</Link>
              </>}
        </nav>
      </div></header>
      <main className="pubmain">{children}</main>
      <footer className="pubfoot"><div className="wrap">
        <span><strong>Council Funds</strong> &middot; Student council finance system</span>
        <span>Sign-ups are limited to <code>{config.allowedEmailDomain}</code> email addresses.</span>
      </div></footer>
    </>
  );
}

/* ------------------------------------------------------------------ logged-in pages: sidebar + <main> */
function NavLink({ to, active, icon, children }) {
  return <Link to={to} className={active ? 'on' : ''}><Icon name={icon} />{children}</Link>;
}

export function AppShell({ children }) {
  const { user, nav, pending, signOut } = useSession();
  const { path } = useRoute();
  useBodyClass('');
  const on = (p) => path === p || path.startsWith(p + '/');
  const level = user?.level;
  const staff = ['admin', 'officer', 'mayor'].includes(level);
  const adminOrMayor = level === 'admin' || level === 'mayor';

  // keep the page at the top and the focus sensible after each navigation
  useEffect(() => { window.scrollTo(0, 0); }, [path]);

  return (
    <>
      <aside className="side">
        <Link className="brand" to="/dashboard"><span className="logo">₱</span><span>Council Funds<small>Student council finance</small></span></Link>
        <nav aria-label="Main">
          <NavLink to="/dashboard" active={on('/dashboard')} icon="home">Dashboard</NavLink>
          <NavLink to="/profile" active={on('/profile')} icon="user">My profile</NavLink>
          <NavLink to="/announcements" active={on('/announcements')} icon="bell">Announcements</NavLink>
          <NavLink to="/payments" active={on('/payments')} icon="receipt">{staff ? 'Payments' : 'My payments'}</NavLink>
          {staff && (
            <>
              <Link to="/requests" className={on('/requests') ? 'on' : ''}><Icon name="inbox" />Requests{pending ? <span className="badge">{pending}</span> : null}</Link>
              <NavLink to="/college-ledger" active={on('/college-ledger')} icon="book">College history</NavLink>
              {level === 'admin' && <NavLink to="/university-ledger" active={on('/university-ledger')} icon="wallet">University fund</NavLink>}
              {adminOrMayor && <NavLink to="/audit" active={on('/audit')} icon="shield">Audit trail</NavLink>}
              {adminOrMayor && <NavLink to="/members" active={on('/members')} icon="users">Members</NavLink>}
              {level === 'admin' && <NavLink to="/mayors" active={on('/mayors')} icon="shield">Mayor assignments</NavLink>}
              {level === 'admin' && <NavLink to="/enrollments" active={on('/enrollments')} icon="users">Enrollment records</NavLink>}
              {level === 'admin' && <NavLink to="/catalog" active={on('/catalog')} icon="book">College/program catalog</NavLink>}
              {adminOrMayor && nav.map((g) => (
                <div key={g.title}>
                  <div className="grp">{g.title}</div>
                  {g.items.map((it) => (
                    <NavLink key={it.key} to={`/crud/${it.key}`} active={on(`/crud/${it.key}`)} icon={it.icon}>{it.title}</NavLink>
                  ))}
                </div>
              ))}
            </>
          )}
        </nav>
        <div className="me">
          <Link className="me-who" to="/profile">
            <Avatar name={user.name} photo={user.profile_photo} />
            <span><strong>{user.name}</strong><Pill kind={user.level}>{user.label}</Pill></span>
          </Link>
          <form onSubmit={(e) => { e.preventDefault(); signOut(); }}>
            <button className="linkbtn" type="submit"><Icon name="logout" />Log out</button>
          </form>
        </div>
      </aside>
      <main className="main">
        <FlashBox />
        {children}
      </main>
    </>
  );
}
