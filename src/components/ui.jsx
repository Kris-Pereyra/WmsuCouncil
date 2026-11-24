import { useState } from 'react';
import { UPLOADS_URL } from '../api.js';
import { Link } from '../router.jsx';
import { useSession } from '../session.jsx';

/* ------------------------------------------------------------------ icons (same artwork as the PHP icon() helper) */
const PATHS = {
  home: <><path d="M3 11.5 12 4l9 7.5" /><path d="M5.5 10v9.5h13V10" /><path d="M10 19.5v-5h4v5" /></>,
  user: <><circle cx="12" cy="8" r="3.6" /><path d="M4.5 20c.8-3.6 3.8-5.4 7.5-5.4s6.7 1.8 7.5 5.4" /></>,
  inbox: <><path d="M4 13.5 6.5 5h11L20 13.5" /><path d="M4 13.5V19h16v-5.5h-4.5a3.5 3.5 0 0 1-7 0z" /></>,
  book: <><path d="M5 4.5h11.5a2 2 0 0 1 2 2V20H7a2 2 0 0 1-2-2z" /><path d="M5 18a2 2 0 0 1 2-2h11.5" /></>,
  users: <><circle cx="9" cy="8.5" r="3.2" /><path d="M3 19.5c.6-3.2 3-4.8 6-4.8s5.4 1.6 6 4.8" /><path d="M16 5.6a3.2 3.2 0 0 1 0 5.8" /><path d="M18 15c1.8.5 2.8 2 3 4.5" /></>,
  student: <><path d="m3 9 9-4.5L21 9l-9 4.5z" /><path d="M7 11.5V16c0 1.2 2.2 2.5 5 2.5s5-1.3 5-2.5v-4.5" /></>,
  badge: <><rect x="4" y="4" width="16" height="16" rx="3" /><circle cx="12" cy="10" r="2.4" /><path d="M7.5 17c.6-2 2.2-3 4.5-3s3.9 1 4.5 3" /></>,
  coins: <><ellipse cx="12" cy="7" rx="7" ry="3" /><path d="M5 7v5c0 1.7 3.1 3 7 3s7-1.3 7-3V7" /><path d="M5 12v5c0 1.7 3.1 3 7 3s7-1.3 7-3v-5" /></>,
  wallet: <><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v3" /><rect x="4" y="8" width="16" height="11" rx="2.5" /><circle cx="15.5" cy="13.5" r="1.1" /></>,
  receipt: <><path d="M6 3.5h12V21l-3-2-3 2-3-2-3 2z" /><path d="M9 8.5h6M9 12h6" /></>,
  folder: <path d="M3.5 7.5a2 2 0 0 1 2-2H10l2 2.5h6.5a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2z" />,
  tag: <><path d="M3.5 12.2V5.5a2 2 0 0 1 2-2h6.7l8.3 8.3a2 2 0 0 1 0 2.8l-5.6 5.6a2 2 0 0 1-2.8 0z" /><circle cx="8.5" cy="8.5" r="1.2" /></>,
  chart: <><path d="M4 20V4" /><path d="M4 20h16" /><path d="M8 16v-4M12.5 16V8M17 16v-6" /></>,
  logout: <><path d="M14 4.5H6.5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2H14" /><path d="M10 12h10M17 8.5 20.5 12 17 15.5" /></>,
  plus: <path d="M12 5v14M5 12h14" />,
  check: <path d="m5 12.5 4.5 4.5L19 7.5" />,
  x: <path d="M6 6l12 12M18 6 6 18" />,
  camera: <><path d="M4 8.5a2 2 0 0 1 2-2h2l1.2-1.8h5.6L16 6.5h2a2 2 0 0 1 2 2V17a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z" /><circle cx="12" cy="12.5" r="3.2" /></>,
  shield: <><path d="M12 3.5 5 6v5.5c0 4.2 2.8 7.4 7 9 4.2-1.6 7-4.8 7-9V6z" /><path d="m9 12 2.2 2.2L15.5 10" /></>,
  bell: <><path d="M6 17.5V11a6 6 0 0 1 12 0v6.5l1.5 1.5h-15z" /><path d="M10 21h4" /></>,
};

export function Icon({ name }) {
  return (
    <svg className="ico" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="1.7"
      strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {PATHS[name] || null}
    </svg>
  );
}

/* ------------------------------------------------------------------ small formatters */
export function money(v, currency = '₱') {
  const n = Number(v) || 0;
  return (n < 0 ? '-' : '') + currency + Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function initials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  const a = (parts[0] || '?').charAt(0);
  const b = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
  return (a + b).toUpperCase();
}

export function fullName(r, short = false) {
  let mid = (r.middle_name || '').trim();
  if (mid && short) mid = mid.charAt(0) + '.';
  return [r.first_name || '', mid, r.last_name || '', r.suffix || ''].join(' ').replace(/\s+/g, ' ').trim();
}

export function Avatar({ name, photo, size = '' }) {
  if (photo && /^[A-Za-z0-9_.-]+$/.test(photo)) {
    return <img className={`avatar ${size}`} src={`${UPLOADS_URL}/avatars/${photo}`} alt="" />;
  }
  return <span className={`avatar ini ${size}`} aria-hidden="true">{initials(name)}</span>;
}

/** Text with line breaks kept (the old pages used nl2br). */
export function Multiline({ text }) {
  const lines = String(text ?? '').split('\n');
  return lines.map((l, i) => (
    <span key={i}>{l}{i < lines.length - 1 ? <br /> : null}</span>
  ));
}

export function Pill({ kind = '', children }) {
  return <span className={`pill ${kind}`}>{children}</span>;
}

/* ------------------------------------------------------------------ messages */
export function FlashBox() {
  const { flash } = useSession();
  if (!flash) return null;
  return <div className={`flash ${flash.type}`}>{flash.msg}</div>;
}

export function ErrorList({ errors, title }) {
  if (!errors || !errors.length) return null;
  return (
    <div className="flash err">
      {title ? <strong>{title}</strong> : null}
      <ul>{errors.map((er, i) => <li key={i}>{er}</li>)}</ul>
    </div>
  );
}

/* ------------------------------------------------------------------ form bits */
export function PasswordField({ label = 'password', ...rest }) {
  const [show, setShow] = useState(false);
  return (
    <span className="password-field">
      <input type={show ? 'text' : 'password'} {...rest} />
      <button className={`password-toggle${show ? ' is-visible' : ''}`} type="button" aria-pressed={show}
        aria-label={`${show ? 'Hide' : 'Show'} ${label}`} onClick={() => setShow((s) => !s)}>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" />
          <circle cx="12" cy="12" r="3" />
          <path className="eye-slash" d="m4 4 16 16" />
        </svg>
      </button>
    </span>
  );
}

export function SchoolYearSelect({ years, value, onChange, name = 'school_year', allLabel = 'All years' }) {
  return (
    <select name={name} value={value} onChange={onChange}>
      <option value="">{allLabel}</option>
      {years.map((y) => <option key={y} value={y}>{y}</option>)}
    </select>
  );
}

/* ------------------------------------------------------------------ page chrome */
export function Head({ title, sub, children, count }) {
  return (
    <div className="head">
      <div>
        <h1>{title}{count !== undefined ? <> <span className="count">{count}</span></> : null}</h1>
        {sub ? <p className="muted">{sub}</p> : null}
      </div>
      {children}
    </div>
  );
}

export function NoAccess({ message }) {
  return (
    <div className="card">
      <h1>No access</h1>
      <p>{message || 'Your account does not have access to this page.'}</p>
      <p><Link className="btn" to="/dashboard">Back to dashboard</Link></p>
    </div>
  );
}

/** Renders loading / error states for a useApi() result, then the page once data arrived. */
export function Async({ state, children }) {
  if (state.error && state.error.status === 403) return <NoAccess message={state.error.message} />;
  if (state.error) return <div className="flash err">{state.error.message}</div>;
  if (!state.data) return <p className="muted">Loading…</p>;
  return children(state.data);
}

export function Empty({ cols, children }) {
  return <tr><td colSpan={cols} className="empty">{children}</td></tr>;
}
