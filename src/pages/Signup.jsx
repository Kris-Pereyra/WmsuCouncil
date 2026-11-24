import { api } from '../api.js';
import { Link } from '../router.jsx';
import { useSession } from '../session.jsx';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { ErrorList, FlashBox, Icon, PasswordField } from '../components/ui.jsx';

const EMPTY = {
  first_name: '', middle_name: '', last_name: '', suffix: '', student_no: '', college: '', program: '',
  year_level: '', school_year: '', contact_no: '', email: '', password: '', password2: '',
};

export default function Signup() {
  usePageTitle('Sign up');
  const { config } = useSession();
  const meta = useApi('meta');
  const { values, set, bind } = useForm(EMPTY);
  const { errors, busy, run } = useSubmit();

  const colleges = meta.data?.colleges || [];
  const programs = meta.data?.programs || {};
  const years = meta.data?.schoolYears || [];
  const suffixes = meta.data?.suffixes || ['Jr.', 'Sr.', 'II', 'III', 'IV', 'V', 'Other'];
  const list = programs[values.college] || [];

  const submit = (e) => {
    e.preventDefault();
    // On success the API answers {redirect: '/verify'}; the app follows it by itself.
    run(() => api.post('signup', values));
  };

  // choosing another college empties the program list, like the old page's script did
  const onCollege = (e) => { set('college', e.target.value); set('program', ''); };

  return (
    <div className="wrap"><div className="authshell">
      <aside className="authside">
        <h2>Join your student council’s books.</h2>
        <p>See where collections go, follow each project’s spending and keep your own details up to date.</p>
        <ul className="checks">
          <li><Icon name="shield" /><span>Only <strong>{config.allowedEmailDomain}</strong> emails are accepted</span></li>
          <li><Icon name="check" />A 6-digit code confirms your mailbox</li>
          <li><Icon name="user" />Add a profile picture after you log in</li>
        </ul>
      </aside>
      <div className="card authcard">
        <h1>Create your account</h1>
        <FlashBox />
        <ErrorList errors={errors} title="Please fix the following:" />
        <form method="post" className="form" autoComplete="off" onSubmit={submit}>
          <div className="fgroup wide">Your name</div>
          <label><span>First name <b>*</b></span><input {...bind('first_name')} maxLength={50} required autoComplete="given-name" /></label>
          <label><span>Middle name <i>(optional)</i></span><input {...bind('middle_name')} maxLength={50} autoComplete="additional-name" /></label>
          <label><span>Last name <b>*</b></span><input {...bind('last_name')} maxLength={50} required autoComplete="family-name" /></label>
          <label><span>Suffix <i>(optional)</i></span>
            <select {...bind('suffix')}>
              <option value="">— none —</option>
              {suffixes.map((s) => <option key={s} value={s}>{s}</option>)}
            </select></label>

          <div className="fgroup wide">School details</div>
          <label><span>Student number <b>*</b></span><input {...bind('student_no')} maxLength={20} required /></label>
          <label><span>School year enrolled <b>*</b></span>
            <select {...bind('school_year')} required>
              <option value="">— choose —</option>
              {years.map((y) => <option key={y} value={y}>{y}</option>)}
            </select></label>
          <label className="wide"><span>College <b>*</b></span>
            <select id="collegeSelect" name="college" value={values.college} onChange={onCollege} required>
              <option value="">— choose your college first —</option>
              {colleges.map((c) => <option key={c} value={c}>{c}</option>)}
            </select>
          </label>
          <label className="wide"><span>Current program <b>*</b></span>
            <select id="programSelect" {...bind('program')} required disabled={values.college === '' || list.length === 0}>
              <option value="">{values.college === '' ? '— choose your college first —' : '— choose your program —'}</option>
              {list.map((p) => <option key={p} value={p}>{p}</option>)}
              {values.college !== '' && list.length === 0 && <option value="">Program list not yet configured — contact the administrator</option>}
            </select>
            <small>Select your college before choosing a program.</small></label>
          <label><span>Year level <b>*</b></span>
            <select {...bind('year_level')} required>
              <option value="">— choose —</option>
              {[1, 2, 3, 4, 5, 6].map((y) => <option key={y} value={String(y)}>Year {y}</option>)}
            </select></label>
          <label><span>Contact number <b>*</b></span>
            <input type="tel" {...bind('contact_no')} maxLength={20} required inputMode="tel" placeholder="0917 123 4567" /></label>

          <div className="fgroup wide">Sign-in details</div>
          <label className="wide"><span>School email <b>*</b></span>
            <input type="email" {...bind('email')} required placeholder={`yourname${config.allowedEmailDomain}`} />
            <small>Must end with {config.allowedEmailDomain}</small></label>
          <label><span>Password <b>*</b></span>
            <PasswordField label="password" {...bind('password')} minLength={8} maxLength={72} required autoComplete="new-password" />
            <small>At least 8 characters</small></label>
          <label><span>Confirm password <b>*</b></span>
            <PasswordField label="confirm password" {...bind('password2')} minLength={8} maxLength={72} required autoComplete="new-password" /></label>
          <div className="actions wide">
            <button className="btn" type="submit" disabled={busy}>Send verification code</button>
            <Link className="btn ghost" to="/login">I already have an account</Link>
          </div>
        </form>
      </div>
    </div></div>
  );
}
