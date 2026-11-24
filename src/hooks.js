import { useCallback, useEffect, useState } from 'react';
import { api } from './api.js';

/** Load data from the API; re-runs when the route or params change. */
export function useApi(route, params = {}) {
  const key = route + '?' + new URLSearchParams(params).toString();
  const [tick, setTick] = useState(0);
  const [state, setState] = useState({ loading: true, data: null, error: null });

  useEffect(() => {
    let alive = true;
    setState((s) => ({ ...s, loading: true, error: null }));
    api.get(route, params)
      .then((data) => alive && setState({ loading: false, data, error: null }))
      .catch((error) => alive && setState({ loading: false, data: null, error }));
    return () => { alive = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, tick]);

  const reload = useCallback(() => setTick((t) => t + 1), []);
  return { ...state, reload };
}

/** Form state: bind('email') gives {name, value, onChange}. */
export function useForm(initial) {
  const [values, setValues] = useState(initial);
  const set = useCallback((name, value) => setValues((v) => ({ ...v, [name]: value })), []);
  const bind = (name) => ({
    name,
    value: values[name] ?? '',
    onChange: (e) => set(name, e.target.value),
  });
  return { values, setValues, set, bind };
}

/** Run an async submit with busy + error-list handling. */
export function useSubmit() {
  const [errors, setErrors] = useState([]);
  const [busy, setBusy] = useState(false);
  const run = useCallback(async (fn) => {
    setBusy(true);
    setErrors([]);
    try {
      return await fn();
    } catch (e) {
      setErrors(e.errors?.length ? e.errors : [e.message]);
      return null;
    } finally {
      setBusy(false);
    }
  }, []);
  return { errors, busy, run, setErrors };
}

export function usePageTitle(title) {
  useEffect(() => {
    document.title = title ? `${title} · Council Funds` : 'Council Funds';
  }, [title]);
}

/** The old pages used <body class="pub"> for public screens and a plain body for the app shell. */
export function useBodyClass(cls) {
  useEffect(() => {
    document.body.className = cls;
    return () => { document.body.className = ''; };
  }, [cls]);
}
