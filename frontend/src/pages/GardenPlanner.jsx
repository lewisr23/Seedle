import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, ApiError } from '../api/client';
import { useAuth } from '../context/AuthContext';

export default function GardenPlanner() {
  const { user } = useAuth();
  const [beds, setBeds] = useState(null);
  const [name, setName] = useState('');
  const [zone, setZone] = useState(user?.hardiness_zone || '');
  const [error, setError] = useState('');

  const load = () => api('/garden-beds').then((res) => setBeds(res.data));

  useEffect(() => {
    load();
  }, []);

  const createBed = async (e) => {
    e.preventDefault();
    if (!name.trim()) return;
    setError('');
    try {
      await api('/garden-beds', { method: 'POST', body: { name, hardiness_zone: zone || null } });
      setName('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not create bed.');
    }
  };

  return (
    <div className="page container">
      <span className="eyebrow">Plan</span>
      <h1 className="page-title">Garden Planner</h1>
      <p className="page-subtitle">
        Lay out your beds, add what's growing, and we'll flag plants that don't get along.
      </p>

      <div className="card section" style={{ padding: 18 }}>
        {error && <div className="alert alert--error">{error}</div>}
        <form onSubmit={createBed} style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
          <input
            type="text"
            placeholder="Bed name, e.g. Back Yard Bed"
            value={name}
            onChange={(e) => setName(e.target.value)}
            style={{ flex: 1, minWidth: 200, padding: '9px 12px', borderRadius: 10, border: '1.5px solid var(--sand-dark)' }}
          />
          <input
            type="number"
            placeholder="Zone"
            value={zone}
            onChange={(e) => setZone(e.target.value)}
            style={{ width: 90, padding: '9px 12px', borderRadius: 10, border: '1.5px solid var(--sand-dark)' }}
          />
          <button className="btn" type="submit">
            Add bed
          </button>
        </form>
      </div>

      {beds === null && <div className="loading">Loading your beds...</div>}
      {beds?.length === 0 && (
        <div className="empty-state">
          <span className="empty-state__icon">🪴</span>
          No garden beds yet: add one above to get started.
        </div>
      )}

      {beds?.map((bed) => (
        <div className="bed-card" key={bed.id}>
          <div className="flex-between">
            <div>
              <strong>{bed.name}</strong>
              {bed.hardiness_zone && <span className="tag" style={{ marginLeft: 8 }}>Zone {bed.hardiness_zone}</span>}
            </div>
            <Link className="btn btn--outline" to={`/garden/${bed.id}`}>
              Manage
            </Link>
          </div>
          <div style={{ marginTop: 10 }}>
            {bed.plants?.length === 0 && <span style={{ fontSize: 13, color: 'var(--ink-500)' }}>Nothing planted yet.</span>}
            {bed.plants?.map((entry) => (
              <span className="plant-chip" key={entry.entry_id}>
                {entry.plant.name}
              </span>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
