import { useEffect, useState } from 'react';
import { api, ApiError } from '../api/client';
import { useAuth } from '../context/AuthContext';

export default function Settings() {
  const { user, setUser } = useAuth();
  const [form, setForm] = useState(null);
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!user) return;
    setForm({
      name: user.name ?? '',
      bio: user.bio ?? '',
      location: user.location ?? '',
      hardiness_zone: user.hardiness_zone ?? '',
      postcode: user.postcode ?? '',
    });
  }, [user]);

  if (!form) return <div className="page container loading">Loading settings...</div>;

  const update = (key) => (e) => {
    setForm((f) => ({ ...f, [key]: e.target.value }));
    setSaved(false);
  };

  const submit = async (event) => {
    event.preventDefault();
    setError('');
    setErrors({});
    setBusy(true);
    try {
      const res = await api('/me', { method: 'PATCH', body: form });
      setUser(res.data);
      setSaved(true);
    } catch (err) {
      if (err instanceof ApiError && err.errors) setErrors(err.errors);
      else setError(err instanceof ApiError ? err.message : 'Could not save your settings.');
    } finally {
      setBusy(false);
    }
  };

  // The API only stores coordinates when the postcode is recognised, so this
  // reflects whether "near me" will actually work rather than what was typed.
  const located = user?.latitude != null;

  return (
    <div className="page container" style={{ maxWidth: 620 }}>
      <h1 className="page-title">Settings</h1>

      {error && <div className="alert alert--error">{error}</div>}
      {saved && <div className="alert alert--success">Saved.</div>}

      <form className="form" onSubmit={submit}>
        <div className="field">
          <label htmlFor="set-name">Name</label>
          <input id="set-name" value={form.name} onChange={update('name')} />
          {errors.name && <span className="error-text">{errors.name[0]}</span>}
        </div>

        <div className="field">
          <label htmlFor="set-bio">Bio</label>
          <textarea id="set-bio" rows={3} value={form.bio} onChange={update('bio')} />
          {errors.bio && <span className="error-text">{errors.bio[0]}</span>}
        </div>

        <div className="field">
          <label htmlFor="set-postcode">Postcode</label>
          <input
            id="set-postcode"
            value={form.postcode}
            onChange={update('postcode')}
            placeholder="BS1 4DF"
            autoComplete="postal-code"
          />
          <span style={{ fontSize: 13, color: 'var(--ink-500)' }}>
            Used to show you swaps you could actually collect. Stored to about a
            kilometre, so it places you in a neighbourhood, not at your door, and
            other people only ever see a distance.
          </span>
          {errors.postcode && <span className="error-text">{errors.postcode[0]}</span>}
          {form.postcode && !located && (
            <span className="error-text">
              We could not find that postcode, so distance search is off. Everything
              else still works.
            </span>
          )}
        </div>

        <div className="field">
          <label htmlFor="set-location">Area name (optional)</label>
          <input
            id="set-location"
            value={form.location}
            onChange={update('location')}
            placeholder="Bedminster"
          />
        </div>

        <div className="field">
          <label htmlFor="set-zone">Hardiness zone</label>
          <input id="set-zone" value={form.hardiness_zone} onChange={update('hardiness_zone')} placeholder="8" />
        </div>

        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Saving...' : 'Save settings'}
        </button>
      </form>
    </div>
  );
}
