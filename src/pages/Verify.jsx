import { useState } from 'react';
import { api } from '../api.js';
import { Link } from '../router.jsx';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { Async } from '../components/ui.jsx';

export default function Verify() {
  usePageTitle('Verify email');
  const state = useApi('verify');
  const { values, bind } = useForm({ code: '' });
  const { errors, busy, run } = useSubmit();
  const [info, setInfo] = useState('');
  const [dev, setDev] = useState(undefined);   // test-mode code after "Send a new code" (undefined = use the one from the page load)

  // Right code: the API answers {redirect: '/login', flash} and the app follows it.
  const verify = (e) => {
    e.preventDefault();
    setInfo('');
    run(() => api.post('verify', { do: 'verify', code: values.code }));
  };

  const resend = (e) => {
    e.preventDefault();
    setInfo('');
    run(async () => {
      const d = await api.post('verify', { do: 'resend' });
      setInfo(d.info || '');
      setDev(d.devCode);
    });
  };

  return (
    <div className="wrap"><div className="authwrap"><div className="card authcard">
      <Async state={state}>
        {(data) => {
          if (!data.masked) return null;      // the API asked us to go back to the sign-up form (redirect is followed automatically)
          const devCode = dev !== undefined ? dev : data.devCode;
          return (
            <>
              <h1>Check your email</h1>
              <p className="muted">We sent a 6-digit code to <strong>{data.masked}</strong>. It works for {data.ttl} minutes.</p>

              {devCode ? (
                <div className="devnote"><strong>Test mode:</strong> email sending is turned off, so your code is shown here:
                  <span className="devcode">{devCode}</span>
                  <small>Set <code>MAIL_MODE</code> to <code>smtp</code> in <code>config.php</code> to send real emails.</small></div>
              ) : null}

              {info ? <div className="flash ok">{info}</div> : null}
              {errors.length > 0 && <div className="flash err">{errors.map((er, i) => <span key={i}>{er}<br /></span>)}</div>}

              <form method="post" className="stack" onSubmit={verify}>
                <label><span>Verification code</span>
                  <input className="codein" {...bind('code')} inputMode="numeric" pattern="\d{6}" maxLength={6} required autoFocus autoComplete="one-time-code" placeholder="••••••" /></label>
                <button className="btn" type="submit" disabled={busy}>Verify and create account</button>
              </form>

              <form method="post" className="inline-form" onSubmit={resend}>
                <span className="muted small">Didn't get it?</span>
                <button className="linkbtn" type="submit" disabled={busy}>Send a new code</button>
                <span className="muted small">·</span>
                <Link className="small" to="/signup">Use a different email</Link>
              </form>
            </>
          );
        }}
      </Async>
    </div></div></div>
  );
}
