import { useMemo, useSyncExternalStore } from 'react';

// A very small hash router: URLs look like  index.html#/payments?school_year=2026-2027
// Hash URLs keep working under XAMPP sub-folders, php -S and Vite without any server rewrite rules.

const subscribe = (cb) => {
  window.addEventListener('hashchange', cb);
  return () => window.removeEventListener('hashchange', cb);
};
const snapshot = () => window.location.hash;

export function parseHash(hash) {
  let h = (hash || '').replace(/^#/, '');
  if (!h.startsWith('/')) h = '/' + h;
  const i = h.indexOf('?');
  const path = (i === -1 ? h : h.slice(0, i)).replace(/\/+$/, '') || '/';
  const search = i === -1 ? '' : h.slice(i + 1);
  return { path, search, query: Object.fromEntries(new URLSearchParams(search)) };
}

export function useRoute() {
  const hash = useSyncExternalStore(subscribe, snapshot, () => '');
  return useMemo(() => parseHash(hash), [hash]);
}

export function navigate(to, { replace = false } = {}) {
  const target = '#' + to;
  if (replace) window.location.replace(target);
  else window.location.hash = to;
  if (window.location.hash === '' && to === '/') window.dispatchEvent(new HashChangeEvent('hashchange'));
}

/** '/crud/:t/:id/edit' against '/crud/budget/4/edit'  ->  { t: 'budget', id: '4' }  (or null). */
export function matchPath(pattern, path) {
  const a = pattern.split('/').filter(Boolean);
  const b = path.split('/').filter(Boolean);
  if (a.length !== b.length) return null;
  const params = {};
  for (let i = 0; i < a.length; i++) {
    if (a[i].startsWith(':')) params[a[i].slice(1)] = decodeURIComponent(b[i]);
    else if (a[i] !== b[i]) return null;
  }
  return params;
}

export function Link({ to, children, ...rest }) {
  return <a href={'#' + to} {...rest}>{children}</a>;
}

/** Build "?a=1&b=2" from an object, skipping empty values. */
export function qs(obj) {
  const q = new URLSearchParams();
  Object.entries(obj || {}).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '' && v !== 0 && v !== '0') q.set(k, String(v));
  });
  const s = q.toString();
  return s ? '?' + s : '';
}
