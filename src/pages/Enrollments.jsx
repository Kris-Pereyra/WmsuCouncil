import { useEffect, useState } from 'react';
import { api } from '../api.js';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { navigate, qs } from '../router.jsx';
import { Async, Empty, ErrorList, Head, SchoolYearSelect } from '../components/ui.jsx';

const STATUSES = ['Verified', 'Pending', 'Not Enrolled', 'Archived'];

/** Record or update one student's enrollment for a school year. The program list depends on the chosen college. */
function EnrollForm({ data, onSaved }) {
  const blank = { student_id: '', college: '', program: '', year_level: '1', school_year: data.defaultYear, enrollment_status: 'Verified' };
  const { values, setValues, set, bind } = useForm(blank);
  const { errors, busy, run } = useSubmit();
  const programs = data.catalog[values.college] || [];

  const submit = (e) => {
    e.preventDefault();
    run(async () => {
      // the API answers with a flash + redirect to /enrollments?school_year=…
      await api.post('enrollments', values);
      setValues(blank);
      onSaved();
    });
  };

  return (
    <section className="card"><h2>Record or update an enrollment</h2>
      <ErrorList errors={errors} />
      <form className="stack" onSubmit={submit}>
        <label>Student
          <select {...bind('student_id')} required>
            <option value="">Choose student</option>
            {data.studentOptions.map((s) => <option key={s.id} value={s.id}>{s.label}</option>)}
          </select>
        </label>
        <div className="two">
          <label>College
            <select name="college" value={values.college} required
              onChange={(e) => setValues((v) => ({ ...v, college: e.target.value, program: '' }))}>
              <option value="">Choose college</option>
              {Object.keys(data.catalog).map((c) => <option key={c} value={c}>{c}</option>)}
            </select>
          </label>
          <label>Program
            <select {...bind('program')} required disabled={programs.length === 0}>
              <option value="">Choose program</option>
              {programs.map((p) => <option key={p} value={p}>{p}</option>)}
            </select>
          </label>
        </div>
        <div className="two">
          <label>Year level
            <select {...bind('year_level')} required>
              {[1, 2, 3, 4, 5, 6].map((i) => <option key={i} value={i}>Year {i}</option>)}
            </select>
          </label>
          <label>School year<input {...bind('school_year')} maxLength={9} placeholder="2026-2027" required /></label>
        </div>
        <label>Enrollment status
          <select {...bind('enrollment_status')}>{STATUSES.map((s) => <option key={s}>{s}</option>)}</select>
        </label>
        <button className="btn" disabled={busy}>Save enrollment record</button>
      </form>
    </section>
  );
}

export default function Enrollments({ query }) {
  usePageTitle('Enrollment records');
  const year = query.school_year || '';
  const college = query.college_id || '';
  const state = useApi('enrollments', { school_year: year, college_id: college });
  const [fYear, setFYear] = useState(year);
  const [fCollege, setFCollege] = useState(college || '0');
  useEffect(() => { setFYear(year); setFCollege(college || '0'); }, [year, college]);

  return (
    <Async state={state}>
      {(d) => (
        <>
          <Head title="School-year enrollment records" sub="Record each student's enrollment for a new school year. Each year is stored separately so earlier records are preserved." />
          <EnrollForm data={d} onSaved={state.reload} />

          <form className="search" onSubmit={(e) => { e.preventDefault(); navigate('/enrollments' + qs({ school_year: fYear, college_id: fCollege })); }}>
            <label className="inline">School year
              <SchoolYearSelect years={d.years} value={fYear} onChange={(e) => setFYear(e.target.value)} />
            </label>
            <label className="inline">College
              <select name="college_id" value={fCollege} onChange={(e) => setFCollege(e.target.value)}>
                <option value="0">All colleges</option>
                {d.colleges.map((c) => <option key={c.college_id} value={c.college_id}>{c.college_name}</option>)}
              </select>
            </label>
            <button className="btn ghost">Filter</button>
          </form>

          <div className="card tablewrap"><table>
            <thead><tr><th>Student</th><th>College / program</th><th>Year</th><th>School year</th><th>Status</th><th>Recorded</th></tr></thead>
            <tbody>
              {d.rows.map((r) => (
                <tr key={r.enrollment_id ?? `${r.student_id}-${r.school_year}`}>
                  <td>{r.last_name}, {r.first_name}<br /><small className="muted">{r.student_no}</small></td>
                  <td>{r.college_name}<br /><small className="muted">{r.program_name_snapshot}</small></td>
                  <td>{Number(r.year_level)}</td>
                  <td>{r.school_year}</td>
                  <td>{r.enrollment_status}</td>
                  <td>{r.created_at}</td>
                </tr>
              ))}
              {!d.rows.length ? <Empty cols={6}>No enrollment records match these filters.</Empty> : null}
            </tbody>
          </table></div>
        </>
      )}
    </Async>
  );
}
