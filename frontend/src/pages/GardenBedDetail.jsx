import { useEffect, useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { api, ApiError } from '../api/client';
import PlotCanvas from '../components/PlotCanvas';
import HarvestForm from '../components/HarvestForm';
import { formatCm, typeIcon } from '../utils/plants';

const ISSUE_LABELS = {
  crowding: 'Too close together',
  companion: 'Poor companions',
  overhang: 'Not enough room',
};

export default function GardenBedDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [bed, setBed] = useState(null);
  const [issues, setIssues] = useState([]);
  const [plants, setPlants] = useState([]);
  const [selectedPlant, setSelectedPlant] = useState('');
  const [pending, setPending] = useState(null);
  const [selectedEntryId, setSelectedEntryId] = useState(null);
  const [warnings, setWarnings] = useState([]);
  const [error, setError] = useState('');
  const [recommendations, setRecommendations] = useState(null);
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState({ name: '', zone: '', width: '', length: '' });

  const load = () =>
    api(`/garden-beds/${id}`).then((res) => {
      setBed(res.data);
      setIssues(res.issues || []);
    });

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

  const fail = (err, fallback) => {
    setError(err instanceof ApiError ? err.message : fallback);
  };

  const startEditing = () => {
    setDraft({
      name: bed.name,
      zone: bed.hardiness_zone || '',
      width: bed.width_cm ? String(bed.width_cm / 100) : '',
      length: bed.length_cm ? String(bed.length_cm / 100) : '',
    });
    setEditing(true);
  };

  // The form talks in metres because that is how anyone describes a bed; the
  // API deals in whole centimetres so nothing downstream carries a float.
  const toCm = (metres) => (metres === '' ? null : Math.round(parseFloat(metres) * 100));

  const saveBed = async (event) => {
    event.preventDefault();
    setError('');
    try {
      await api(`/garden-beds/${id}`, {
        method: 'PUT',
        body: {
          name: draft.name,
          hardiness_zone: draft.zone || null,
          width_cm: toCm(draft.width),
          length_cm: toCm(draft.length),
        },
      });
      setEditing(false);
      load();
    } catch (err) {
      fail(err, 'Could not save changes.');
    }
  };

  const deleteBed = async () => {
    if (!window.confirm(`Delete "${bed.name}" and everything planted in it?`)) return;
    await api(`/garden-beds/${id}`, { method: 'DELETE' });
    navigate('/garden');
  };

  const addPlant = async (event) => {
    event.preventDefault();
    if (!selectedPlant) return;
    setError('');
    setWarnings([]);
    const plant = plants.find((p) => String(p.id) === String(selectedPlant));

    // With a plan drawn, adding a plant means saying where it goes, so the
    // picker arms the canvas rather than dropping it in an invisible pile.
    if (bed.has_plot) {
      setPending({ kind: 'new', plant });
      return;
    }

    try {
      const res = await api(`/garden-beds/${id}/plants`, { method: 'POST', body: { plant_id: selectedPlant } });
      setWarnings(res.warnings || []);
      setSelectedPlant('');
      load();
    } catch (err) {
      fail(err, 'Could not add plant.');
    }
  };

  const place = async (x, y) => {
    if (!pending) return;
    setError('');
    try {
      if (pending.kind === 'new') {
        const res = await api(`/garden-beds/${id}/plants`, {
          method: 'POST',
          body: { plant_id: pending.plant.id, x_cm: x, y_cm: y },
        });
        setWarnings(res.warnings || []);
        setIssues(res.issues || []);
        setSelectedPlant('');
      } else {
        const res = await api(`/garden-beds/${id}/plants/${pending.entry.entry_id}`, {
          method: 'PATCH',
          body: { x_cm: x, y_cm: y },
        });
        setIssues(res.issues || []);
      }
      setPending(null);
      load();
    } catch (err) {
      fail(err, 'Could not place that there.');
      setPending(null);
    }
  };

  const move = async (entryId, x, y) => {
    // Move the plant on screen first: a drag that waits for the network
    // before the plant follows your finger feels broken.
    setBed((current) => ({
      ...current,
      plants: current.plants.map((entry) =>
        entry.entry_id === entryId ? { ...entry, x_cm: x, y_cm: y } : entry
      ),
    }));

    try {
      const res = await api(`/garden-beds/${id}/plants/${entryId}`, {
        method: 'PATCH',
        body: { x_cm: x, y_cm: y },
      });
      setIssues(res.issues || []);
    } catch (err) {
      fail(err, 'Could not move that plant.');
      load();
    }
  };

  const removeEntry = async (entryId) => {
    await api(`/garden-beds/${id}/plants/${entryId}`, { method: 'DELETE' });
    if (selectedEntryId === entryId) setSelectedEntryId(null);
    load();
  };

  if (!bed) return <div className="page container loading">Loading...</div>;

  const unplaced = bed.plants.filter((entry) => entry.x_cm === null || entry.y_cm === null);
  const selectedEntry = bed.plants.find((entry) => entry.entry_id === selectedEntryId) || null;

  return (
    <div className="page container">
      <Link to="/garden" style={{ fontSize: 13 }}>
        ← Back to Garden Planner
      </Link>

      {editing ? (
        <form className="card" onSubmit={saveBed} style={{ padding: 18, marginTop: 12, maxWidth: 520 }}>
          <div className="field" style={{ marginBottom: 12 }}>
            <label htmlFor="bed-name">Bed name</label>
            <input
              id="bed-name"
              value={draft.name}
              onChange={(e) => setDraft({ ...draft, name: e.target.value })}
              required
            />
          </div>
          <div className="field" style={{ marginBottom: 12 }}>
            <label htmlFor="bed-zone">Hardiness zone</label>
            <input
              id="bed-zone"
              type="number"
              value={draft.zone}
              onChange={(e) => setDraft({ ...draft, zone: e.target.value })}
              placeholder="e.g. 8"
            />
          </div>
          <div className="plot-size-fields">
            <div className="field">
              <label htmlFor="bed-width">Width (m)</label>
              <input
                id="bed-width"
                type="number"
                step="0.1"
                min="0.3"
                value={draft.width}
                onChange={(e) => setDraft({ ...draft, width: e.target.value })}
                placeholder="2.4"
              />
            </div>
            <div className="field">
              <label htmlFor="bed-length">Length (m)</label>
              <input
                id="bed-length"
                type="number"
                step="0.1"
                min="0.3"
                value={draft.length}
                onChange={(e) => setDraft({ ...draft, length: e.target.value })}
                placeholder="1.2"
              />
            </div>
          </div>
          <div style={{ display: 'flex', gap: 8, marginTop: 14 }}>
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
            <p className="page-subtitle" style={{ marginBottom: 0 }}>
              {bed.hardiness_zone && <>Zone {bed.hardiness_zone}</>}
              {bed.hardiness_zone && bed.has_plot && ' · '}
              {bed.has_plot && `${formatCm(bed.width_cm)} × ${formatCm(bed.length_cm)}`}
            </p>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button className="btn btn--outline btn--sm" onClick={startEditing}>
              Edit bed
            </button>
            <button className="btn btn--ghost btn--sm" onClick={deleteBed}>
              Delete bed
            </button>
          </div>
        </div>
      )}

      {error && <div className="alert alert--error">{error}</div>}
      {warnings.length > 0 && (
        <div className="alert alert--warning">
          {warnings.map((w, i) => (
            <div key={i}>⚠️ {w.note}</div>
          ))}
        </div>
      )}

      <div className="section">
        {!bed.has_plot ? (
          <div className="card plot-empty">
            <h2 className="section-title" style={{ marginTop: 0 }}>
              Draw this bed to scale
            </h2>
            <p>
              Give the bed a width and a length and you can lay your plants out on it, each one drawn at
              the room it actually needs.
            </p>
            <button className="btn btn--sm" onClick={startEditing}>
              Set the size
            </button>
          </div>
        ) : (
          <>
            <div className="plot-toolbar">
              <form onSubmit={addPlant} className="plot-toolbar__picker">
                <select
                  aria-label="Plant to add"
                  value={selectedPlant}
                  onChange={(e) => setSelectedPlant(e.target.value)}
                >
                  <option value="">Choose a plant...</option>
                  {plants.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                      {p.spacing_cm ? ` (${formatCm(p.spacing_cm)})` : ''}
                    </option>
                  ))}
                </select>
                <button className="btn btn--sm" type="submit" disabled={!selectedPlant}>
                  Add
                </button>
              </form>
              {pending ? (
                <div className="plot-toolbar__hint is-armed">
                  Tap the plot to put the {pending.plant?.name || pending.entry.plant.name} down.
                  <button className="btn btn--ghost btn--sm" onClick={() => setPending(null)}>
                    Cancel
                  </button>
                </div>
              ) : (
                <div className="plot-toolbar__hint">Drag anything on the plot to move it.</div>
              )}
            </div>

            <PlotCanvas
              bed={bed}
              entries={bed.plants}
              issues={issues}
              selectedEntryId={selectedEntryId}
              onSelectEntry={setSelectedEntryId}
              onMove={move}
              pendingPlant={pending ? pending.plant || pending.entry.plant : null}
              onPlace={place}
            />

            {issues.length > 0 && (
              <ul className="plot-issues">
                {issues.map((issue, index) => (
                  <li key={index} className={`plot-issues__item plot-issues__item--${issue.kind}`}>
                    <strong>{ISSUE_LABELS[issue.kind] || 'Check this'}</strong>
                    <span>{issue.note}</span>
                  </li>
                ))}
              </ul>
            )}
          </>
        )}
      </div>

      {unplaced.length > 0 && (
        <div className="section">
          <h2 className="section-title">
            {bed.has_plot ? 'Not on the plan yet' : "What's planted"}
          </h2>
          {unplaced.map((entry) => (
            <span className="plant-chip" key={entry.entry_id}>
              {typeIcon(entry.plant.type)} {entry.plant.name}
              {bed.has_plot && (
                <button onClick={() => setPending({ kind: 'entry', entry })} title="Place on the plot">
                  place
                </button>
              )}
              <button onClick={() => removeEntry(entry.entry_id)} title="Remove">
                ×
              </button>
            </span>
          ))}
        </div>
      )}

      {selectedEntry && (
        <div className="section card plot-selected">
          <div className="flex-between" style={{ gap: 12, flexWrap: 'wrap' }}>
            <h2 className="section-title" style={{ margin: 0 }}>
              {typeIcon(selectedEntry.plant.type)} {selectedEntry.plant.name}
            </h2>
            <button className="btn btn--ghost btn--sm" onClick={() => removeEntry(selectedEntry.entry_id)}>
              Pull it up
            </button>
          </div>

          <div className="plot-selected__meta">
            <label htmlFor="planted-at">Planted</label>
            <input
              id="planted-at"
              type="date"
              value={selectedEntry.planted_at ? selectedEntry.planted_at.slice(0, 10) : ''}
              onChange={async (event) => {
                const value = event.target.value || null;
                try {
                  await api(`/garden-beds/${id}/plants/${selectedEntry.entry_id}`, {
                    method: 'PATCH',
                    body: { planted_at: value },
                  });
                  load();
                } catch (err) {
                  fail(err, 'Could not save that date.');
                }
              }}
            />
            {selectedEntry.ready_on && (
              <span className="plot-selected__ready">
                Should be ready around {new Date(selectedEntry.ready_on).toLocaleDateString()}
              </span>
            )}
          </div>

          <HarvestForm
            bedId={id}
            entry={selectedEntry}
            onLogged={load}
            onRemoved={load}
          />
        </div>
      )}

      {bed.hardiness_zone && (
        <div className="section">
          <h2 className="section-title">What can I plant right now?</h2>
          {recommendations === null && <div className="loading">Loading suggestions...</div>}
          {recommendations?.length === 0 && (
            <div className="empty-state">
              Nothing in our reference list is typically planted this month for your zone.
            </div>
          )}
          <div className="recommend-grid">
            {recommendations?.map((p) => (
              <div className="recommend-card" key={p.id}>
                <strong>{p.name}</strong>
                {p.sun_requirement.replace('_', ' ')} sun · {p.water_needs} water
                {p.spacing_cm ? ` · ${formatCm(p.spacing_cm)} apart` : ''}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
