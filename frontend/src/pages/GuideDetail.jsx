import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api/client';

export default function GuideDetail() {
  const { slug } = useParams();
  const [guide, setGuide] = useState(null);

  useEffect(() => {
    setGuide(null);
    api(`/guides/${slug}`).then((res) => setGuide(res.data));
  }, [slug]);

  if (guide === null) return <div className="page container loading">Loading...</div>;

  return (
    <div className="page container">
      <Link to="/guides" style={{ fontSize: 13, color: 'var(--ink-500)' }}>
        ← All guides
      </Link>

      <div style={{ marginTop: 14 }}>
        <span className="tag">{guide.category_label}</span>
        <h1 className="page-title" style={{ marginTop: 10 }}>
          {guide.title}
        </h1>
        <p className="page-subtitle" style={{ marginBottom: 8 }}>
          {guide.read_minutes} min read
        </p>
      </div>

      {guide.plant && (
        <div className="card" style={{ padding: 16, marginBottom: 26, background: 'var(--green-50)', maxWidth: 560 }}>
          <strong>Growing {guide.plant.name}?</strong>
          <p style={{ fontSize: 13, margin: '6px 0 8px', color: 'var(--ink-500)' }}>
            Sun: {guide.plant.sun_requirement.replace('_', ' ')} · Water: {guide.plant.water_needs} · Zones{' '}
            {guide.plant.min_zone}-{guide.plant.max_zone}
          </p>
          <Link to="/" className="btn btn--sm btn--outline">
            Shop {guide.plant.name} seeds &amp; plants
          </Link>
        </div>
      )}

      <div className="guide-body">
        {guide.body.split('\n\n').map((para, i) => (
          <p key={i}>{para}</p>
        ))}
      </div>
    </div>
  );
}
