import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api/client';
import SaveButton from '../components/SaveButton';
import ProductCard from '../components/ProductCard';
import { useCart } from '../context/CartContext';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

export default function PlantDetail() {
  const { id } = useParams();
  const { addItem } = useCart();
  const [plant, setPlant] = useState(null);
  const [products, setProducts] = useState([]);

  useEffect(() => {
    setPlant(null);
    api(`/plants/${id}`).then((res) => setPlant(res.data));
  }, [id]);

  useEffect(() => {
    if (plant) {
      api('/products', { params: { plant_id: plant.id, in_stock: 1, per_page: 8 } }).then((res) =>
        setProducts(res.data)
      );
    }
  }, [plant?.id]);

  if (plant === null) return <div className="page container loading">Loading...</div>;

  const currentMonth = new Date().getMonth() + 1;
  const plantableNow = plant.planting_months.includes(currentMonth);

  return (
    <div className="page container">
      <Link to="/plants" style={{ fontSize: 13, color: 'var(--ink-500)' }}>
        ← Plant library
      </Link>

      <div className="card" style={{ padding: 28, marginTop: 14 }}>
        <span className="tag">{plant.type}</span>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 10 }}>
          <h1 className="page-title" style={{ margin: 0 }}>
            {plant.name}
          </h1>
          <SaveButton type="plant" id={plant.id} returnTo={`/plants/${plant.id}`} size={24} />
        </div>
        {plant.description && <p style={{ color: 'var(--ink-700)', maxWidth: '62ch' }}>{plant.description}</p>}

        <div className="plant-facts">
          <div>
            <span>Sun</span>
            <strong>{plant.sun_requirement.replace('_', ' ')}</strong>
          </div>
          <div>
            <span>Water</span>
            <strong>{plant.water_needs}</strong>
          </div>
          <div>
            <span>Soil</span>
            <strong>{plant.soil_type || ': '}</strong>
          </div>
          <div>
            <span>Hardiness</span>
            <strong>
              Zones {plant.min_zone}: {plant.max_zone}
            </strong>
          </div>
          <div>
            <span>Time to harvest</span>
            <strong>{plant.days_to_maturity ? `${plant.days_to_maturity} days` : ': '}</strong>
          </div>
        </div>
      </div>

      <div className="section" style={{ marginTop: 28 }}>
        <h2 className="section-title">When to plant</h2>
        {plantableNow && (
          <div className="alert alert--success" style={{ maxWidth: 420 }}>
            ✅ This is a good month to plant {plant.name}.
          </div>
        )}
        <div className="month-strip">
          {MONTHS.map((m, i) => (
            <span key={m} className={`month-chip${plant.planting_months.includes(i + 1) ? ' active' : ''}`}>
              {m}
            </span>
          ))}
        </div>
      </div>

      <div className="section">
        <h2 className="section-title">Companion planting</h2>
        <div className="companion-columns">
          <div>
            <h3 className="companion-heading companion-heading--good">Grows well with</h3>
            {plant.good_companions?.length > 0 ? (
              plant.good_companions.map((c) => (
                <Link key={c.id} to={`/plants/${c.id}`} className="plant-chip">
                  {c.name}
                </Link>
              ))
            ) : (
              <p style={{ fontSize: 14, color: 'var(--ink-500)' }}>No recorded good companions.</p>
            )}
          </div>
          <div>
            <h3 className="companion-heading companion-heading--bad">Keep away from</h3>
            {plant.bad_companions?.length > 0 ? (
              plant.bad_companions.map((c) => (
                <Link key={c.id} to={`/plants/${c.id}`} className="plant-chip plant-chip--bad">
                  {c.name}
                </Link>
              ))
            ) : (
              <p style={{ fontSize: 14, color: 'var(--ink-500)' }}>No recorded conflicts.</p>
            )}
          </div>
        </div>
      </div>

      {products.length > 0 && (
        <div className="section">
          <h2 className="section-title">{plant.name} on the swap shelf</h2>
          <div className="grid">
            {products.map((p) => (
              <ProductCard key={p.id} product={p} onAddToCart={(prod) => addItem(prod, 1)} />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
