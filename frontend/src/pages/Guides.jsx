import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';

const CATEGORIES = [
  { value: '', label: 'All guides' },
  { value: 'getting_started', label: 'Getting Started' },
  { value: 'soil_and_feeding', label: 'Soil & Feeding' },
  { value: 'watering', label: 'Watering' },
  { value: 'pest_control', label: 'Pest Control' },
  { value: 'seasonal', label: 'Seasonal' },
  { value: 'tools', label: 'Tools' },
  { value: 'composting', label: 'Composting' },
];

export default function Guides() {
  const [category, setCategory] = useState('');
  const [q, setQ] = useState('');
  const [guides, setGuides] = useState(null);

  useEffect(() => {
    setGuides(null);
    const timeout = setTimeout(() => {
      api('/guides', { params: { category: category || undefined, q: q || undefined, per_page: 50 } }).then((res) =>
        setGuides(res.data)
      );
    }, 250);
    return () => clearTimeout(timeout);
  }, [category, q]);

  return (
    <div className="page container">
      <span className="eyebrow">Learn</span>
      <h1 className="page-title">Guides &amp; tips</h1>
      <p className="page-subtitle">
        Practical, no-nonsense advice — from your first vegetable bed to fixing a smelly compost bin.
      </p>

      <div className="filters">
        <input
          type="text"
          placeholder="Search guides..."
          value={q}
          onChange={(e) => setQ(e.target.value)}
          style={{ minWidth: 220 }}
        />
      </div>

      <div className="category-filters">
        {CATEGORIES.map((c) => (
          <button
            key={c.value}
            className={`category-pill${category === c.value ? ' active' : ''}`}
            onClick={() => setCategory(c.value)}
          >
            {c.label}
          </button>
        ))}
      </div>

      {guides === null && <div className="loading">Loading guides...</div>}
      {guides?.length === 0 && (
        <div className="empty-state">
          <span className="empty-state__icon">🔍</span>
          No guides match that search.
        </div>
      )}

      <div className="grid">
        {guides?.map((g) => (
          <Link key={g.id} to={`/guides/${g.slug}`} className="guide-card">
            <span className="tag">{g.category_label}</span>
            <h3 className="guide-card__title">{g.title}</h3>
            <p className="guide-card__excerpt">{g.excerpt}</p>
            <div className="guide-card__meta">
              <span>{g.read_minutes} min read</span>
              {g.plant && <span>🌿 {g.plant.name}</span>}
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
