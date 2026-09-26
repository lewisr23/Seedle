import { useState } from 'react';
import { api, ApiError } from '../api/client';

const UNITS = ['g', 'kg', 'count', 'bunch', 'punnet', 'trug'];

const today = () => new Date().toISOString().slice(0, 10);

/** "1.5 kg", "6" for a count, or just "picked" when nothing was measured. */
function amount(harvest) {
  if (harvest.quantity === null || harvest.quantity === undefined) return 'picked';
  if (harvest.unit === 'count' || !harvest.unit) return String(harvest.quantity);
  return `${harvest.quantity} ${harvest.unit}`;
}

/**
 * Logging what came off one plant, and the running list of what already has.
 *
 * Quantity is optional throughout: plenty of picking is "a colander of beans"
 * rather than a weight, and a log that insists on a number just stops getting
 * filled in.
 */
export default function HarvestForm({ bedId, entry, onLogged, onRemoved }) {
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ harvested_at: today(), quantity: '', unit: 'kg', notes: '' });
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const harvests = entry.harvests || [];

  const submit = async (event) => {
    event.preventDefault();
    setError('');
    setErrors({});
    setBusy(true);
    try {
      await api(`/garden-beds/${bedId}/plants/${entry.entry_id}/harvests`, {
        method: 'POST',
        body: {
          harvested_at: form.harvested_at,
          quantity: form.quantity === '' ? null : Number(form.quantity),
          unit: form.quantity === '' ? null : form.unit,
          notes: form.notes || null,
        },
      });
      setForm({ harvested_at: today(), quantity: '', unit: form.unit, notes: '' });
      setOpen(false);
      onLogged?.();
    } catch (err) {
      if (err instanceof ApiError && err.errors) setErrors(err.errors);
      else setError(err instanceof ApiError ? err.message : 'Could not log that harvest.');
    } finally {
      setBusy(false);
    }
  };

  const remove = async (harvestId) => {
    await api(`/harvests/${harvestId}`, { method: 'DELETE' }).catch(() => {});
    onRemoved?.();
  };

  return (
    <div className="harvest-form">
      <div className="flex-between" style={{ gap: 10, flexWrap: 'wrap' }}>
        <h3 className="harvest-form__title">Harvest log</h3>
        {!open && (
          <button className="btn btn--sm" onClick={() => setOpen(true)}>
            Log a harvest
          </button>
        )}
      </div>

      {error && <div className="alert alert--error">{error}</div>}

      {open && (
        <form onSubmit={submit} className="harvest-form__fields">
          <div className="field">
            <label htmlFor="harvested-at">Date</label>
            <input
              id="harvested-at"
              type="date"
              max={today()}
              value={form.harvested_at}
              onChange={(e) => setForm({ ...form, harvested_at: e.target.value })}
              required
            />
            {errors.harvested_at && <span className="error-text">{errors.harvested_at[0]}</span>}
          </div>
          <div className="field">
            <label htmlFor="harvest-quantity">How much (optional)</label>
            <div className="harvest-form__quantity">
              <input
                id="harvest-quantity"
                type="number"
                step="0.01"
                min="0"
                value={form.quantity}
                onChange={(e) => setForm({ ...form, quantity: e.target.value })}
                placeholder="1.5"
              />
              <select
                aria-label="Unit"
                value={form.unit}
                onChange={(e) => setForm({ ...form, unit: e.target.value })}
              >
                {UNITS.map((unit) => (
                  <option key={unit} value={unit}>
                    {unit}
                  </option>
                ))}
              </select>
            </div>
            {errors.quantity && <span className="error-text">{errors.quantity[0]}</span>}
          </div>
          <div className="field">
            <label htmlFor="harvest-notes">Notes</label>
            <input
              id="harvest-notes"
              value={form.notes}
              onChange={(e) => setForm({ ...form, notes: e.target.value })}
              placeholder="Sweet, a bit small"
            />
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button className="btn btn--sm" type="submit" disabled={busy}>
              {busy ? 'Saving...' : 'Save'}
            </button>
            <button className="btn btn--ghost btn--sm" type="button" onClick={() => setOpen(false)}>
              Cancel
            </button>
          </div>
        </form>
      )}

      {harvests.length === 0 ? (
        <p className="harvest-form__empty">Nothing picked from this one yet.</p>
      ) : (
        <ul className="harvest-form__list">
          {harvests.map((harvest) => (
            <li key={harvest.id}>
              <span className="harvest-form__date">
                {new Date(harvest.harvested_at).toLocaleDateString()}
              </span>
              <span className="harvest-form__amount">{amount(harvest)}</span>
              {harvest.notes && <span className="harvest-form__notes">{harvest.notes}</span>}
              <button onClick={() => remove(harvest.id)} title="Remove this entry" aria-label="Remove harvest">
                ×
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
