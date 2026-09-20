import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, ApiError } from '../api/client';
import Avatar from '../components/Avatar';
import { useAuth } from '../context/AuthContext';
import { timeAgo } from '../utils/time';

export default function Wanted() {
  const { user } = useAuth();
  const [wants, setWants] = useState(null);
  const [plants, setPlants] = useState([]);
  const [form, setForm] = useState({ title: '', description: '', plant_id: '' });
  const [open, setOpen] = useState(false);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const load = () => api('/wants').then((res) => setWants(res.data));

  useEffect(() => {
    load();
    api('/plants', { params: { per_page: 100 } }).then((res) => setPlants(res.data));
  }, []);

  const submit = async (event) => {
    event.preventDefault();
    setError('');
    setErrors({});
    setBusy(true);
    try {
      await api('/wants', {
        method: 'POST',
        body: { ...form, plant_id: form.plant_id || null },
      });
      setForm({ title: '', description: '', plant_id: '' });
      setOpen(false);
      await load();
    } catch (err) {
      if (err instanceof ApiError && err.errors) setErrors(err.errors);
      else setError(err instanceof ApiError ? err.message : 'Could not post that.');
    } finally {
      setBusy(false);
    }
  };

  const close = async (id) => {
    await api(`/wants/${id}/close`, { method: 'PATCH' }).catch(() => {});
    load();
  };

  if (wants === null) return <div className="page container loading">Loading requests...</div>;

  return (
    <div className="page container">
      <div className="flex-between">
        <div>
          <h1 className="page-title">Wanted</h1>
          <p className="page-subtitle">What people are looking for. Got a spare? Say hello.</p>
        </div>
        {user && !open && (
          <button className="btn" onClick={() => setOpen(true)}>
            Ask for something
          </button>
        )}
      </div>

      {error && <div className="alert alert--error">{error}</div>}

      {open && (
        <form className="card" style={{ padding: 18, margin: '14px 0' }} onSubmit={submit}>
          <div className="field">
            <label htmlFor="want-title">What are you after?</label>
            <input
              id="want-title"
              value={form.title}
              onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
              placeholder="Looking for rhubarb crowns"
              required
            />
            {errors.title && <span className="error-text">{errors.title[0]}</span>}
          </div>

          <div className="field">
            <label htmlFor="want-plant">Plant (optional)</label>
            <select
              id="want-plant"
              value={form.plant_id}
              onChange={(e) => setForm((f) => ({ ...f, plant_id: e.target.value }))}
            >
              <option value="">Not sure / not listed</option>
              {plants.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
            <span style={{ fontSize: 13, color: 'var(--ink-500)' }}>
              Pick one and we will tell you when somebody puts it up.
            </span>
          </div>

          <div className="field">
            <label htmlFor="want-desc">Anything else?</label>
            <textarea
              id="want-desc"
              rows={3}
              value={form.description}
              onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))}
              placeholder="Happy to collect, can swap for tomato seeds."
            />
          </div>

          <div style={{ display: 'flex', gap: 8 }}>
            <button className="btn" type="submit" disabled={busy || !form.title.trim()}>
              {busy ? 'Posting...' : 'Post request'}
            </button>
            <button className="btn btn--ghost" type="button" onClick={() => setOpen(false)}>
              Cancel
            </button>
          </div>
        </form>
      )}

      {wants.length === 0 && (
        <div className="empty-state">
          <span className="empty-state__icon">🔎</span>
          Nobody is asking for anything yet.
        </div>
      )}

      {wants.map((want) => (
        <div className="card" key={want.id} style={{ padding: 16, marginBottom: 12 }}>
          <div className="flex-between">
            <div style={{ display: 'flex', gap: 10, minWidth: 0 }}>
              <Avatar name={want.user?.username} size={34} />
              <div>
                <strong>{want.title}</strong>
                <div style={{ fontSize: 13, color: 'var(--ink-500)' }}>
                  <Link to={`/u/${want.user?.username}`}>{want.user?.username}</Link>
                  {' · '}
                  {timeAgo(want.created_at)}
                  {want.plant && ` · ${want.plant.name}`}
                </div>
                {want.description && (
                  <p style={{ margin: '6px 0 0', fontSize: 14 }}>{want.description}</p>
                )}
              </div>
            </div>
            {want.is_mine && want.is_open && (
              <button className="btn btn--ghost btn--sm" onClick={() => close(want.id)}>
                Mark as found
              </button>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}
