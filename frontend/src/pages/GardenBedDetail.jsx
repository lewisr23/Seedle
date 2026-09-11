import { useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api, ApiError } from '../api/client';

export default function GardenBedDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [bed, setBed] = useState(null);
  const [plants, setPlants] = useState([]);
  const [selectedPlant, setSelectedPlant] = useState('');
  const [warnings, setWarnings] = useState([]);
  const [error, setError] = useState('');
  const [recommendations, setRecommendations] = useState(null);
  const [editing, setEditing] = useState(false);
  const [nameDraft, setNameDraft] = useState('');
  const [zoneDraft, setZoneDraft] = useState('');

  const load = () => api(`/garden-beds/${id}`).then((res) => setBed(res.data));

  useEffect(() => {
    load();
    api('/plants', { params: { per_page: 100 } }).then((res) => setPlants(res.data));
  }, [id]);

  useEffect(() => {
    if (bed?.hardiness_zone) {
      api('/plants/recommendations', { params: { zone: bed.hardiness_zone } }).then((res) =>
        setRecommendations(res.data)
      );
    }
  }, [bed?.hardiness_zone]);

  const addPlant = async (e) => {
    e.preventDefault();
    if (!selectedPlant) return;
    setError('');
    setWarnings([]);
    try {
      const res = await api(`/garden-beds/${id}/plants`, { method: 'POST', body: { plant_id: selectedPlant } });
      setWarnings(res.warnings || []);
      setSelectedPlant('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not add plant.');
    }
  };

  const removePlant = async (entryId) => {
    await api(`/garden-beds/${id}/plants/${entryId}`, { method: 'DELETE' });
    load();
  };

  const startEditing = () => {
    setNameDraft(bed.name);
    setZoneDraft(bed.hardiness_zone || '');
    setEditing(true);
  };

  const saveBed = async (e) => {
    e.preventDefault();
    setError('');
    try {
      await api(`/garden-beds/${id}`, {
        method: 'PUT',
        body: { name: nameDraft, hardiness_zone: zoneDraft || null },
      });
      setEditing(false);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not save changes.');
    }
  };

  const deleteBed = async () => {
    if (!window.confirm(`Delete "${bed.name}" and everything planted in it?`)) return;
    await api(`/garden-beds/${id}`, { method: 'DELETE' });
    navigate('/garden');
  };

  if (!bed) return <div className="page container loading">Loading...</div>;

  return (
    <div className="page container">
      <Link to="/garden" style={{ fontSize: 13 }}>
        ← Back to Garden Planner
      </Link>

      {editing ? (
        <form className="card" onSubmit={saveBed} style={{ padding: 18, marginTop: 12, maxWidth: 520 }}>
          <div className="field" style={{ marginBottom: 12 }}>
            <label>Bed name</label>
            <input value={nameDraft} onChange={(e) => setNameDraft(e.target.value)} required />
          </div>
          <div className="field" style={{ marginBottom: 14 }}>
            <label>Hardiness zone</label>
            <input
              type="number"
              value={zoneDraft}
              onChange={(e) => setZoneDraft(e.target.value)}
              placeholder="e.g. 8"
            />
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button className="btn btn--sm" type="submit">
              Save
            </button>
            <button className="btn btn--ghost btn--sm" type="button" onClick={() => setEditing(false)}>
              Cancel
            </button>
          </div>
        </form>
      ) : (
        <div className="flex-between" style={{ marginTop: 8, gap: 12, flexWrap: 'wrap' }}>
          <div>
            <h1 className="page-title" style={{ marginBottom: 2 }}>
              {bed.name}
            </h1>
            {bed.hardiness_zone && <p className="page-subtitle" style={{ marginBottom: 0 }}>Zone {bed.hardiness_zone}</p>}
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button className="btn btn--outline btn--sm" onClick={startEditing}>
              Rename
            </button>
            <button className="btn btn--ghost btn--sm" onClick={deleteBed}>
              Delete bed
            </button>
          </div>
        </div>
      )}

      <div className="section">
        <h2 className="section-title">What's planted</h2>
        {bed.plants.length === 0 && <div className="empty-state">Nothing planted yet.</div>}
        {bed.plants.map((entry) => (
          <span className="plant-chip" key={entry.entry_id}>
            {entry.plant.name}
            <button onClick={() => removePlant(entry.entry_id)} title="Remove">
              ×
            </button>
          </span>
        ))}
      </div>

      <div className="section card" style={{ padding: 16, maxWidth: 480 }}>
        <h2 className="section-title">Add a plant</h2>
        {error && <div className="alert alert--error">{error}</div>}
        {warnings.length > 0 && (
          <div className="alert alert--warning">
            {warnings.map((w, i) => (
              <div key={i}>⚠️ {w.note}</div>
            ))}
          </div>
        )}
        <form onSubmit={addPlant} style={{ display: 'flex', gap: 10 }}>
          <select value={selectedPlant} onChange={(e) => setSelectedPlant(e.target.value)} style={{ flex: 1 }}>
            <option value="">Choose a plant...</option>
            {plants.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
          <button className="btn" type="submit">
            Add
          </button>
        </form>
      </div>

      {bed.hardiness_zone && (
        <div className="section">
          <h2 className="section-title">What can I plant right now?</h2>
          {recommendations === null && <div className="loading">Loading suggestions...</div>}
          {recommendations?.length === 0 && (
            <div className="empty-state">Nothing in our reference list is typically planted this month for your zone.</div>
          )}
          <div className="recommend-grid">
            {recommendations?.map((p) => (
              <div className="recommend-card" key={p.id}>
                <strong>{p.name}</strong>
                {p.sun_requirement.replace('_', ' ')} sun · {p.water_needs} water
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
