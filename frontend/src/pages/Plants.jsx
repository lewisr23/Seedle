import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';

const TYPES = [
  { value: '', label: 'All plants' },
  { value: 'vegetable', label: 'Vegetables' },
  { value: 'fruit', label: 'Fruit' },
  { value: 'herb', label: 'Herbs' },
  { value: 'flower', label: 'Flowers' },
  { value: 'tree', label: 'Trees' },
  { value: 'shrub', label: 'Shrubs' },
];

const SUN_OPTIONS = [
  { value: '', label: 'Any sun' },
  { value: 'full_sun', label: 'Full sun' },
  { value: 'partial_sun', label: 'Partial sun' },
  { value: 'shade', label: 'Shade' },
];

const WATER_OPTIONS = [
  { value: '', label: 'Any watering' },
  { value: 'low', label: 'Low water' },
  { value: 'medium', label: 'Medium water' },
  { value: 'high', label: 'High water' },
];

const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];

const TYPE_ICONS = {
  vegetable: '🥕',
  fruit: '🍓',
  herb: '🌿',
  flower: '🌼',
  tree: '🌳',
  shrub: '🪴',
};

export default function Plants() {
  const { user } = useAuth();
  const [plants, setPlants] = useState(null);
  const [q, setQ] = useState('');
  const [type, setType] = useState('');
  const [sun, setSun] = useState('');
  const [water, setWater] = useState('');
  const [zone, setZone] = useState('');
  const [month, setMonth] = useState('');

  useEffect(() => {
    setPlants(null);
    const timeout = setTimeout(() => {
      api('/plants', {
        params: {
          q: q || undefined,
          type: type || undefined,
          sun_requirement: sun || undefined,
          water_needs: water || undefined,
          zone: zone || undefined,
          month: month || undefined,
        },
      }).then((res) => setPlants(res.data));
    }, 250);

    return () => clearTimeout(timeout);
  }, [q, type, sun, water, zone, month]);

  const useMyZone = () => setZone(user?.hardiness_zone || '');
  const usePlantableNow = () => setMonth(String(new Date().getMonth() + 1));

  const hasFilters = q || type || sun || water || zone || month;

  const clearAll = () => {
    setQ('');
    setType('');
    setSun('');
    setWater('');
    setZone('');
    setMonth('');
  };

  return (
    <div className="page container">
      <span className="eyebrow">Reference</span>
      <h1 className="page-title">Plant library</h1>
      <p className="page-subtitle">
        Care details, hardiness ranges and companion planting for every plant in the GrowGuide database.
      </p>

      <div className="filters">
        <input
          type="text"
          placeholder="Search plants..."
          value={q}
          onChange={(e) => setQ(e.target.value)}
          style={{ minWidth: 200 }}
        />
        <select value={sun} onChange={(e) => setSun(e.target.value)}>
          {SUN_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        <select value={water} onChange={(e) => setWater(e.target.value)}>
          {WATER_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        <select value={month} onChange={(e) => setMonth(e.target.value)}>
          <option value="">Any planting month</option>
          {MONTHS.map((m, i) => (
            <option key={m} value={i + 1}>
              Plant in {m}
            </option>
          ))}
        </select>
        <input
          type="number"
          placeholder="Zone"
          value={zone}
          onChange={(e) => setZone(e.target.value)}
          style={{ width: 90 }}
        />
        {user?.hardiness_zone && zone !== user.hardiness_zone && (
          <button className="btn btn--outline btn--sm" onClick={useMyZone}>
            My zone ({user.hardiness_zone})
          </button>
        )}
        <button className="btn btn--outline btn--sm" onClick={usePlantableNow}>
          Plantable this month
        </button>
        {hasFilters && (
          <button className="btn btn--ghost btn--sm" onClick={clearAll}>
            Clear all
          </button>
        )}
      </div>

      <div className="category-filters">
        {TYPES.map((t) => (
          <button
            key={t.value}
            className={`category-pill${type === t.value ? ' active' : ''}`}
            onClick={() => setType(t.value)}
          >
            {t.label}
          </button>
        ))}
      </div>

      {plants === null && <div className="loading">Loading plants...</div>}
      {plants?.length === 0 && (
        <div className="empty-state">
          <span className="empty-state__icon">🌱</span>
          No plants match those filters.
          {hasFilters && (
            <div style={{ marginTop: 10 }}>
              <button className="btn btn--outline btn--sm" onClick={clearAll}>
                Clear filters
              </button>
            </div>
          )}
        </div>
      )}

      <div className="grid">
        {plants?.map((plant) => (
          <Link key={plant.id} to={`/plants/${plant.id}`} className="plant-card">
            <span className="plant-card__icon">{TYPE_ICONS[plant.type] || '🌱'}</span>
            <div>
              <h3 className="plant-card__name">{plant.name}</h3>
              <span className="plant-card__type">{plant.type}</span>
            </div>
            <div className="plant-card__meta">
              <span>☀️ {plant.sun_requirement.replace('_', ' ')}</span>
              <span>💧 {plant.water_needs}</span>
              <span>
                🌡️ Zones {plant.min_zone}–{plant.max_zone}
              </span>
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
