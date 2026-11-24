import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { api, setCsrf, setHooks } from './api.js';
import { navigate, parseHash, useRoute } from './router.jsx';

const Ctx = createContext(null);
export const useSession = () => useContext(Ctx);

const DEFAULT_CONFIG = { allowedEmailDomain: '@wmsu.edu.ph', currency: '₱', codeTtl: 10 };

export function SessionProvider({ children }) {
  const [state, setState] = useState({ loading: true, user: null, nav: [], pending: 0, config: DEFAULT_CONFIG, dbError: null });
  const [flash, setFlash] = useState(null);
  const survive = useRef(false);   // true when the flash was set together with a redirect, so it must outlive the next navigation

  const refresh = useCallback(async () => {
    try {
      const d = await api.get('session');
      setCsrf(d.csrf);
      setState({ loading: false, user: d.user, nav: d.nav || [], pending: d.pending || 0, config: d.config || DEFAULT_CONFIG, dbError: d.dbError || null });
    } catch (e) {
      setState((s) => ({ ...s, loading: false, user: null, dbError: e.message }));
    }
  }, []);

  // Log out: the API answers with a flash + redirect to /login; the user state is cleared in the same tick,
  // so the guards in App.jsx never see a stale "logged in" user on the login page.
  const signOut = useCallback(async () => {
    try {
      await api.post('logout');
    } finally {
      setState((s) => ({ ...s, user: null, nav: [], pending: 0 }));
    }
  }, []);

  useEffect(() => {
    setHooks({
      // Every successful answer may carry {flash, redirect, csrf}: handle them in one place.
      onResult: (data) => {
        if (data.csrf) setCsrf(data.csrf);
        if (data.flash) {
          const target = data.redirect ? parseHash('#' + data.redirect).path : null;
          survive.current = !!target && target !== parseHash(window.location.hash).path;
          setFlash(data.flash);
        }
        if (data.redirect) navigate(data.redirect);
      },
      onAuthLost: () => setState((s) => ({ ...s, user: null, nav: [], pending: 0 })),
    });
    refresh();
  }, [refresh]);

  // A flash lives until the next page change (like the old one-time session flash).
  const { path } = useRoute();
  useEffect(() => {
    if (survive.current) survive.current = false;
    else setFlash(null);
    window.scrollTo(0, 0);
  }, [path]);

  const value = useMemo(() => ({ ...state, flash, setFlash, refresh, signOut }), [state, flash, refresh, signOut]);
  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}
