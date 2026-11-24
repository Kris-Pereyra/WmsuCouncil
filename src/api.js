// Tiny fetch wrapper for the PHP JSON API (api/index.php?r=<route>).
// - sends the CSRF token header on every write
// - turns error answers into ApiError (with a list of messages)
// - lets the app react to "flash" / "redirect" answers in one place (see session.jsx)

// Relative on purpose: from /dist/ (XAMPP) or / (php -S, Vite proxy) the API is always one level up.
export const API_URL = import.meta.env.VITE_API_URL || '../api/index.php';
export const UPLOADS_URL = import.meta.env.VITE_UPLOADS_URL || '../uploads';

export class ApiError extends Error {
  constructor(status, body) {
    const errors = body?.errors?.length ? body.errors : body?.error ? [body.error] : [];
    super(errors[0] || `The server answered with an error (${status}).`);
    this.status = status;
    this.code = body?.code || null;
    this.errors = errors.length ? errors : [this.message];
    this.body = body || {};
  }
}

let csrfToken = '';
const hooks = { onResult: () => {}, onAuthLost: () => {} };

export const setCsrf = (t) => { if (t) csrfToken = t; };
export const setHooks = (h) => Object.assign(hooks, h);

function url(route, params) {
  const q = new URLSearchParams({ r: route });
  Object.entries(params || {}).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') q.set(k, String(v));
  });
  return `${API_URL}?${q.toString()}`;
}

async function refreshCsrf() {
  const res = await fetch(url('session'), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  const data = await res.json().catch(() => ({}));
  setCsrf(data.csrf);
}

async function request(method, route, { params, body, form } = {}, retry = true) {
  const opts = { method, credentials: 'same-origin', headers: { Accept: 'application/json' } };
  if (method !== 'GET') {
    if (!csrfToken) await refreshCsrf();
    opts.headers['X-CSRF-Token'] = csrfToken;
    if (form) {
      opts.body = form;                              // multipart (file uploads): the browser sets the boundary
    } else {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body || {});
    }
  }

  let res;
  try {
    res = await fetch(url(route, params), opts);
  } catch {
    throw new ApiError(0, { error: 'Cannot reach the server. Is the PHP back end running?' });
  }
  const text = await res.text();
  let data;
  try {
    data = text ? JSON.parse(text) : {};
  } catch {
    throw new ApiError(res.status, { error: 'The server sent an unexpected answer. Check that PHP is running and config.php is correct.' });
  }

  if (!res.ok) {
    if (res.status === 400 && data.code === 'csrf' && retry) {   // token went stale (e.g. session expired): fetch a new one once
      await refreshCsrf();
      return request(method, route, { params, body, form }, false);
    }
    if (res.status === 401 && data.code === 'auth') hooks.onAuthLost();
    throw new ApiError(res.status, data);
  }
  hooks.onResult(data);
  return data;
}

export const api = {
  get: (route, params) => request('GET', route, { params }),
  post: (route, body, params) => request('POST', route, { body, params }),
  upload: (route, form, params) => request('POST', route, { form, params }),
};
