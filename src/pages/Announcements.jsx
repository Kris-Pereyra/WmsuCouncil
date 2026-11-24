import { api } from '../api.js';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { useSession } from '../session.jsx';
import { Async, ErrorList, Multiline, Pill } from '../components/ui.jsx';

const TYPES = ['General', 'Event', 'Payment', 'Meeting', 'Project', 'Donation'];
const BLANK = { title: '', announcement_type: 'General', body: '', event_date: '', deadline: '', school_year: '' };

export default function Announcements() {
  usePageTitle('Announcements');
  const { user } = useSession();
  const state = useApi('announcements');
  const { values, setValues, bind } = useForm(BLANK);
  const { errors, busy, run } = useSubmit();

  const publish = (e) => {
    e.preventDefault();
    run(async () => {
      await api.post('announcements', values);
      setValues(BLANK);
      state.reload();
    });
  };

  return (
    <>
      <div className="head"><div><h1>Announcements</h1><p className="muted">University-wide and your college’s published notices.</p></div></div>
      <Async state={state}>
        {({ rows, canPublish }) => (
          <>
            {canPublish && (
              <section className="card">
                <h2>Publish announcement</h2>
                <ErrorList errors={errors} />
                <form className="stack" onSubmit={publish}>
                  <label>Title<input {...bind('title')} maxLength={180} required /></label>
                  <label>Type
                    <select {...bind('announcement_type')}>{TYPES.map((t) => <option key={t}>{t}</option>)}</select>
                  </label>
                  {user.level === 'admin'
                    ? <p className="muted">Audience: All WMSU students (university-wide)</p>
                    : <p className="muted">Audience: {user.college ?? 'Your college'}</p>}
                  <label>Announcement<textarea {...bind('body')} rows={5} maxLength={10000} required /></label>
                  <div className="two">
                    <label>Event date (optional)<input type="date" {...bind('event_date')} /></label>
                    <label>Deadline (optional)<input type="date" {...bind('deadline')} /></label>
                  </div>
                  <label>School year (optional)<input {...bind('school_year')} placeholder="2026-2027" maxLength={9} /></label>
                  <button className="btn" disabled={busy}>Publish</button>
                </form>
              </section>
            )}
            <div className="stack">
              {rows.map((a) => (
                <article className="card" key={a.announcement_id ?? `${a.publish_at}-${a.title}`}>
                  <div className="head">
                    <div>
                      <Pill>{a.announcement_type}</Pill> <small className="muted">{a.college_name ?? 'WMSU University-wide'}</small>
                      <h2>{a.title}</h2>
                    </div>
                    <small className="muted">{a.publish_at}</small>
                  </div>
                  <p><Multiline text={a.body} /></p>
                  <small className="muted">
                    {a.event_date ? <>Event: {a.event_date} · </> : null}
                    {a.deadline ? <>Deadline: {a.deadline} · </> : null}
                    {a.school_year ?? ''}
                  </small>
                </article>
              ))}
              {!rows.length && <div className="card empty">No published announcements yet.</div>}
            </div>
          </>
        )}
      </Async>
    </>
  );
}
