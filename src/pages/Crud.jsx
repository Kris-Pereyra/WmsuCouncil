import { useState } from 'react';
import { Link, navigate, qs, useRoute } from '../router.jsx';
import { api } from '../api.js';
import { useApi, useForm, usePageTitle, useSubmit } from '../hooks.js';
import { Async, Empty, ErrorList } from '../components/ui.jsx';

/**
 * Generic table editor for students, fund sources, collections, budgets, projects, expense categories and expenses.
 * The API describes each table (fields, dropdown options, who may write); this page only draws it:
 *   #/crud/:t  (list + search + delete confirmation)   #/crud/:t/new   #/crud/:t/:id/edit
 */
export default function Crud({ params, query }) {
  const { path } = useRoute();
  const { t, id } = params;
  if (path.endsWith('/new')) return <CrudForm t={t} />;
  if (id) return <CrudForm t={t} id={id} />;
  return <CrudList t={t} query={query} />;
}

function UnknownTable() {
  return <p>Unknown table. <Link to="/dashboard">Back to dashboard</Link></p>;
}

function ReadOnlyNotice({ t }) {
  return (
    <>
      <div className="flash err">Your role can view these records but not change them.</div>
      <p><Link className="btn ghost" to={`/crud/${t}`}>Back</Link></p>
    </>
  );
}

/* ------------------------------------------------------------------ list */
function CrudList({ t, query }) {
  const state = useApi('crud', { t, q: query.q });
  const sub = useSubmit();
  const [confirm, setConfirm] = useState(null);   // the row waiting for "Yes, delete"
  usePageTitle(state.data?.title);

  if (state.error?.status === 404) return <UnknownTable />;

  return (
    <Async state={state}>
      {(data) => {
        const low = (s) => String(s).toLowerCase();

        if (confirm) {
          const doDelete = async () => {
            const res = await sub.run(() => api.post('crud', { do: 'delete', id: confirm.id }, { t }));
            if (res) { setConfirm(null); state.reload(); }
          };
          return (
            <>
              <div className="head"><h1>Delete {low(data.single)}?</h1></div>
              <ErrorList errors={sub.errors} />
              <div className="card">
                <p>You are about to permanently delete this record:</p>
                <dl className="detail">
                  {data.fields.map((f) => (
                    <div key={f.name} style={{ display: 'contents' }}>
                      <dt>{f.label}</dt><dd>{confirm.cells[f.name]}</dd>
                    </div>
                  ))}
                </dl>
                <div className="actions">
                  <button className="btn danger" type="button" disabled={sub.busy} onClick={doDelete}>Yes, delete</button>
                  <a className="btn ghost" href={`#/crud/${t}`} onClick={(e) => { e.preventDefault(); setConfirm(null); }}>Cancel</a>
                </div>
              </div>
            </>
          );
        }

        return (
          <>
            <div className="head">
              <h1>{data.title} <span className="count">{data.rows.length}</span></h1>
              {data.canWrite
                ? <Link className="btn" to={`/crud/${t}/new`}>+ Add {low(data.single)}</Link>
                : <span className="pill closed">View only</span>}
            </div>

            <SearchBar key={data.q} t={t} q={data.q} title={data.title} />

            <div className="card tablewrap">
              <table>
                <thead><tr>
                  <th>#</th>
                  {data.fields.map((f) => <th key={f.name} className={f.type === 'money' ? 'num' : ''}>{f.label}</th>)}
                  <th></th>
                </tr></thead>
                <tbody>
                  {data.rows.map((r) => (
                    <tr key={r.id}>
                      <td className="muted">{r.id}</td>
                      {data.fields.map((f) => <td key={f.name} className={f.type === 'money' ? 'num' : ''}>{r.cells[f.name]}</td>)}
                      <td className="rowact">
                        {data.canWrite && (
                          <>
                            <Link to={`/crud/${t}/${r.id}/edit`}>Edit</Link>
                            <a className="del" href={`#/crud/${t}`} onClick={(e) => { e.preventDefault(); sub.setErrors([]); setConfirm(r); }}>Delete</a>
                          </>
                        )}
                      </td>
                    </tr>
                  ))}
                  {!data.rows.length && (
                    <Empty cols={data.fields.length + 2}>
                      {data.q !== '' ? `No matches for “${data.q}”.` : (data.canWrite ? 'No records yet. Click “Add” to create the first one.' : 'No records yet.')}
                    </Empty>
                  )}
                </tbody>
              </table>
            </div>
          </>
        );
      }}
    </Async>
  );
}

function SearchBar({ t, q, title }) {
  const { values, bind } = useForm({ q });
  const submit = (e) => { e.preventDefault(); navigate(`/crud/${t}` + qs({ q: values.q.trim() })); };
  return (
    <form className="search" onSubmit={submit}>
      <input type="search" {...bind('q')} placeholder={`Search ${title.toLowerCase()}…`} />
      <button className="btn ghost" type="submit">Search</button>
      {q !== '' && <Link className="btn ghost" to={`/crud/${t}`}>Clear</Link>}
    </form>
  );
}

/* ------------------------------------------------------------------ add / edit */
function CrudForm({ t, id }) {
  const { query } = useRoute();
  const state = useApi('crud', { t, view: 'form', id });
  if (state.error?.status === 404) return <UnknownTable />;
  return (
    <Async state={state}>
      {(data) => {
        if (!data.canWrite) return <ReadOnlyNotice t={t} />;
        if (id && !data.record) {
          return (
            <>
              <div className="flash err">That record no longer exists.</div>
              <p><Link className="btn ghost" to={`/crud/${t}`}>Back to {data.title.toLowerCase()}</Link></p>
            </>
          );
        }
        return <Form data={data} t={t} id={id} query={query} />;
      }}
    </Async>
  );
}

function initialValues(data, editing, query) {
  const v = {};
  for (const f of data.fields) {
    if (f.type === 'derived' || data.hidden.includes(f.name)) continue;
    if (editing) {
      v[f.name] = data.record.raw[f.name] ?? '';
    } else if (query[f.name] !== undefined) {
      v[f.name] = String(query[f.name]);                       // e.g. #/crud/project/new?request_id=3
    } else if (f.fk === 'officer' && data.myOfficerId) {
      v[f.name] = String(data.myOfficerId);
    } else {
      v[f.name] = f.default ?? '';
    }
  }
  return v;
}

function Form({ data, t, id, query }) {
  const editing = !!id;
  const { values, bind } = useForm(() => initialValues(data, editing, query));
  const { errors, busy, run } = useSubmit();
  usePageTitle(data.title);
  const single = data.single.toLowerCase();

  const submit = async (e) => {
    e.preventDefault();
    // success: the API answers with a flash + redirect to the list, which the app handles
    await run(() => api.post('crud', { do: 'save', id: editing ? id : 0, ...values }, { t }));
  };

  return (
    <>
      <div className="head">
        <h1>{editing ? 'Edit' : 'Add'} {single}</h1>
        <Link className="btn ghost" to={`/crud/${t}`}>Back to {data.title.toLowerCase()}</Link>
      </div>

      <ErrorList errors={errors} title="Please fix the following:" />

      <form className="card form" onSubmit={submit}>
        {data.fields.map((f) => {
          if (f.type === 'derived' || data.hidden.includes(f.name)) return null;
          return <Field key={f.name} f={f} bind={bind(f.name)} value={values[f.name]} data={data} />;
        })}
        <div className="actions wide">
          <button className="btn" type="submit" disabled={busy}>{editing ? 'Save changes' : `Add ${single}`}</button>
          <Link className="btn ghost" to={`/crud/${t}`}>Cancel</Link>
        </div>
      </form>
    </>
  );
}

function Field({ f, bind, value, data }) {
  const req = !f.optional;
  let control;

  if (f.type === 'fk') {
    const opts = data.fk[f.fk] || [];
    control = (
      <>
        <select {...bind} required={req}>
          <option value="">— choose —</option>
          {opts.map((o) => <option key={o.id} value={String(o.id)}>{o.label}</option>)}
        </select>
        {!opts.length && (
          <small className="warn">Nothing to choose yet —{' '}
            {f.fk === 'fund_request'
              ? <Link to="/requests">review requests</Link>
              : <Link to={`/crud/${f.fk}/new`}>add one first</Link>}.</small>
        )}
      </>
    );
  } else if (f.type === 'select') {
    const options = f.options || [];
    control = (
      <select {...bind} required={req}>
        <option value="">{req ? '— choose —' : '—'}</option>
        {value !== '' && !options.includes(value) && <option value={value}>{value} (current)</option>}
        {f.groups
          ? Object.entries(f.groups).map(([label, list]) => (
              <optgroup key={label} label={label}>{list.map((o) => <option key={o} value={o}>{o}</option>)}</optgroup>
            ))
          : options.map((o) => <option key={o} value={o}>{o}</option>)}
      </select>
    );
  } else if (f.type === 'phone') {
    control = <input type="tel" {...bind} maxLength={f.max} required={req} inputMode="tel" placeholder="09XX XXX XXXX" />;
  } else if (f.type === 'textarea') {
    control = <textarea {...bind} rows={3} maxLength={f.max} required={req} />;
  } else if (f.type === 'money') {
    control = <input type="number" {...bind} step="0.01" min={f.positive ? '0.01' : '0'} required={req} inputMode="decimal" />;
  } else if (f.type === 'number') {
    control = <input type="number" {...bind} min={f.min} max={f.max} required={req} />;
  } else if (f.type === 'date') {
    control = <input type="date" {...bind} required={req} />;
  } else {
    control = (
      <>
        <input type="text" {...bind} maxLength={f.max} required={req}
          list={f.suggest ? `dl_${f.name}` : undefined}
          {...(f.name === 'school_year' ? { pattern: '\\d{4}-\\d{4}', placeholder: '2026-2027' } : {})} />
        {f.suggest && <datalist id={`dl_${f.name}`}>{f.suggest.map((s) => <option key={s} value={s} />)}</datalist>}
      </>
    );
  }

  return (
    <label className={f.type === 'textarea' ? 'wide' : ''}>
      <span>{f.label}{req ? <> <b>*</b></> : <> <i>(optional)</i></>}</span>
      {control}
      {f.hint ? <small>{f.hint}</small> : null}
    </label>
  );
}
