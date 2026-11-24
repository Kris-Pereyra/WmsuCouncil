import { useEffect, useRef, useState } from 'react';
import { api } from '../api.js';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { useSession } from '../session.jsx';
import { Async, Avatar, ErrorList, Empty, Icon, PasswordField, Pill, money } from '../components/ui.jsx';

const PHOTO_NOTE = 'JPG, PNG or WebP, up to 2 MB. It is cropped to a square.';

const levelLabel = (u) =>
  u.level === 'admin' ? 'Administrator' : u.level === 'mayor' ? 'College Mayor' : u.level === 'officer' ? 'Council officer' : 'Student';

/* ------------------------------------------------------------------ profile picture (preview + save + remove) */
function PhotoCard({ user, busy, run, refresh }) {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [note, setNote] = useState(PHOTO_NOTE);
  const input = useRef(null);

  // free the temporary preview URL when it is replaced or the page closes
  useEffect(() => () => { if (preview) URL.revokeObjectURL(preview); }, [preview]);

  const pick = (e) => {
    const f = e.target.files[0];
    if (!f) { setFile(null); setPreview(null); return; }
    if (!/^image\/(jpeg|png|webp)$/.test(f.type) || f.size > 2 * 1024 * 1024) {
      e.target.value = '';
      setFile(null);
      setPreview(null);
      setNote('Please choose a JPG, PNG or WebP picture under 2 MB.');
      return;
    }
    setFile(f);
    setPreview(URL.createObjectURL(f));
    setNote(f.name);
  };

  const reset = () => {
    if (input.current) input.current.value = '';
    setFile(null);
    setPreview(null);
    setNote(PHOTO_NOTE);
  };

  const save = (e) => {
    e.preventDefault();
    if (!file) return;
    const fd = new FormData();
    fd.append('do', 'photo');
    fd.append('photo', file);
    run(async () => {
      await api.upload('profile', fd);
      reset();
      await refresh();
    });
  };

  const remove = () => {
    if (!window.confirm('Remove your profile picture?')) return;
    run(async () => {
      await api.post('profile', { do: 'photo_remove' });
      reset();
      await refresh();
    });
  };

  return (
    <section className="card">
      <h2>Profile picture</h2>
      <form className="photoedit" encType="multipart/form-data" onSubmit={save}>
        <span>
          {preview
            ? <img className="avatar xl" src={preview} alt="" />
            : <Avatar name={user.name} photo={user.profile_photo} size="xl" />}
        </span>
        <div>
          <label className="filebtn"><Icon name="camera" /><span>Choose picture</span>
            <input ref={input} type="file" name="photo" accept="image/jpeg,image/png,image/webp" onChange={pick} /></label>
          <p className="muted small">{note}</p>
          <div className="actions">
            <button className="btn" type="submit" disabled={!file || busy}><Icon name="check" />Save picture</button>
            {user.profile_photo
              ? <button className="btn ghost warn" type="button" disabled={busy} onClick={remove}>Remove</button>
              : null}
          </div>
        </div>
      </form>
    </section>
  );
}

/* ------------------------------------------------------------------ page */
export default function Profile() {
  usePageTitle('My profile');
  const { user, refresh } = useSession();
  const isAdmin = user.level === 'admin';
  const state = useApi('profile');
  const { errors, busy, run } = useSubmit();

  const details = useForm({
    display_name: user.display_name ?? '',
    program: user.program ?? '',
    year_level: String(user.year_level ?? 1),
    contact_no: user.contact_no ?? '',
  });
  const pw = useForm({ current: '', new: '', new2: '' });

  const saveDetails = (e) => {
    e.preventDefault();
    run(async () => {
      await api.post('profile', isAdmin
        ? { do: 'profile', display_name: details.values.display_name }
        : { do: 'profile', program: details.values.program, year_level: details.values.year_level, contact_no: details.values.contact_no });
      await refresh();
    });
  };

  const changePassword = (e) => {
    e.preventDefault();
    run(async () => {
      await api.post('profile', { do: 'password', ...pw.values });
      pw.setValues({ current: '', new: '', new2: '' });
    });
  };

  return (
    <>
      <div className="head"><h1>My profile</h1></div>
      <ErrorList errors={errors} />

      <PhotoCard user={user} busy={busy} run={run} refresh={refresh} />

      <Async state={state}>
        {({ terms, payments, today, allowedPrograms }) => {
          const positions = user.positions || [];
          const past = terms.filter((t) => !(t.term_start <= today && (t.term_end === null || t.term_end >= today)));
          const programs = allowedPrograms || [];
          const current = String(user.program ?? '');
          return (
            <>
              <div className="two">
                <section className="card">
                  <h2>Account</h2>
                  <dl className="detail">
                    <dt>Name</dt><dd>{user.name}</dd>
                    <dt>Email</dt><dd>{user.email}</dd>
                    {!isAdmin && (
                      <>
                        <dt>Student No.</dt><dd>{user.student_no}</dd>
                        <dt>Program</dt><dd>{user.program}</dd>
                        <dt>Year level</dt><dd>Year {parseInt(user.year_level, 10) || 0}</dd>
                      </>
                    )}
                    <dt>Access</dt><dd><Pill kind={user.level}>{levelLabel(user)}</Pill></dd>
                  </dl>
                </section>

                <section className="card">
                  <h2>Student council</h2>
                  {isAdmin
                    ? <p>You are the <strong>administrator</strong>. You assign College Mayors; each Mayor manages ordinary officer positions within their college.</p>
                    : positions.length
                      ? (
                        <>
                          <p className="council yes">✔ You are part of the council.</p>
                          <ul className="plain">
                            {positions.map((pos, i) => (
                              <li key={i}><strong>{pos.position}</strong> <span className="muted">since {pos.term_start}{pos.term_end ? `, until ${pos.term_end}` : ''}</span></li>
                            ))}
                          </ul>
                        </>
                      )
                      : <p className="council no">You are not currently part of the council.</p>}
                  {!isAdmin && <p className="muted small">Only the assigned College Mayor can manage ordinary officer positions.</p>}
                  {terms.length > positions.length && (
                    <>
                      <h3>Past terms</h3>
                      <ul className="plain">
                        {past.map((t, i) => (
                          <li key={i}>{t.position} <span className="muted">{t.term_start} → {t.term_end ?? ''}</span></li>
                        ))}
                      </ul>
                    </>
                  )}
                </section>
              </div>

              <div className="two">
                <section className="card">
                  <h2>Edit details</h2>
                  <form className="stack" onSubmit={saveDetails}>
                    {isAdmin
                      ? <label><span>Display name</span><input {...details.bind('display_name')} maxLength={80} required /></label>
                      : (
                        <>
                          <label><span>Program</span>
                            <select {...details.bind('program')} required>
                              <option value="">— choose —</option>
                              {current !== '' && !programs.includes(current) && <option value={current}>{current} (current)</option>}
                              {programs.map((o) => <option key={o} value={o}>{o}</option>)}
                            </select></label>
                          <label><span>Year level</span>
                            <select {...details.bind('year_level')}>
                              {[1, 2, 3, 4, 5, 6].map((y) => <option key={y} value={String(y)}>Year {y}</option>)}
                            </select></label>
                          <label><span>Contact number</span><input type="tel" {...details.bind('contact_no')} maxLength={20} placeholder="0917 123 4567" /></label>
                          <p className="muted small">Name, student number, college and email cannot be changed here. Program choices are limited to your registered college.</p>
                        </>
                      )}
                    <div><button className="btn" type="submit" disabled={busy}>Save</button></div>
                  </form>
                </section>

                <section className="card">
                  <h2>Change password</h2>
                  <form className="stack" onSubmit={changePassword}>
                    <label><span>Current password</span>
                      <PasswordField label="current password" {...pw.bind('current')} required autoComplete="current-password" /></label>
                    <label><span>New password</span>
                      <PasswordField label="new password" {...pw.bind('new')} minLength={8} maxLength={72} required autoComplete="new-password" /></label>
                    <label><span>Repeat new password</span>
                      <PasswordField label="repeated new password" {...pw.bind('new2')} minLength={8} maxLength={72} required autoComplete="new-password" /></label>
                    <div><button className="btn" type="submit" disabled={busy}>Change password</button></div>
                  </form>
                </section>
              </div>

              {!isAdmin && (
                <section className="card">
                  <h2>My payments</h2>
                  <div className="tablewrap">
                    <table>
                      <thead><tr><th>Date</th><th>Receipt</th><th>Source</th><th className="num">Amount</th></tr></thead>
                      <tbody>
                        {payments.map((r, i) => (
                          <tr key={i}>
                            <td className="nw">{r.collection_date}</td><td className="nw">{r.receipt_no}</td>
                            <td>{r.source_name}</td><td className="num">{money(r.amount)}</td>
                          </tr>
                        ))}
                        {!payments.length && <Empty cols={4}>No payments recorded under your student number yet.</Empty>}
                      </tbody>
                    </table>
                  </div>
                </section>
              )}
            </>
          );
        }}
      </Async>
    </>
  );
}
