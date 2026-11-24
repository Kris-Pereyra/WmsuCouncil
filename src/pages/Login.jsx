import { api } from '../api.js';
import { Link, navigate } from '../router.jsx';
import { useSession } from '../session.jsx';
import { useForm, usePageTitle, useSubmit } from '../hooks.js';
import { FlashBox, Icon, PasswordField } from '../components/ui.jsx';

// Only same-site paths are allowed as a "go back to" target (like safe_next() in the PHP page).
const safeNext = (n) => (typeof n === 'string' && /^\/[^/\\:]/.test(n) ? n : '/dashboard');

export default function Login({ query }) {
  usePageTitle('Log in');
  const { config, refresh } = useSession();
  const { values, bind } = useForm({ email: '', password: '' });
  const { errors, busy, run } = useSubmit();

  const submit = (e) => {
    e.preventDefault();
    run(async () => {
      await api.post('login', { email: values.email, password: values.password });
      await refresh();                       // load the new session (user + sidebar) before leaving this page
      navigate(safeNext(query?.next));
    });
  };

  return (
    <div className="wrap"><div className="authshell">
      <aside className="authside">
        <h2>Every peso, accounted for.</h2>
        <p>Log in to see the fund balance, project budgets and, if you are on the council, record collections and request funding for events.</p>
        <ul className="checks">
          <li><Icon name="wallet" />Live balance from the actual records</li>
          <li><Icon name="inbox" />Officers submit requests to their College Mayor</li>
          <li><Icon name="shield" />Role-based access for every account</li>
        </ul>
      </aside>
      <div className="card authcard">
        <h1>Welcome back</h1>
        <p className="muted">Log in with your school email, or the administrator email.</p>
        <FlashBox />
        {errors.length > 0 && <div className="flash err">{errors[0]}</div>}
        <form method="post" className="stack" onSubmit={submit}>
          <label><span>Email</span>
            <input type="email" {...bind('email')} required autoFocus autoComplete="username" /></label>
          <label><span>Password</span>
            <PasswordField label="password" {...bind('password')} required autoComplete="current-password" /></label>
          <button className="btn block" type="submit" disabled={busy}>Log in</button>
        </form>
        <p className="muted small">New here? <Link to="/signup">Create an account</Link> with your <code>{config.allowedEmailDomain}</code> email.</p>
      </div>
    </div></div>
  );
}
