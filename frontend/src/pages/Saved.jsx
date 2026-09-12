import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import ProductCard from '../components/ProductCard';
import SaveButton from '../components/SaveButton';
import { useSaved } from '../context/SavedContext';

export default function Saved() {
  const [saved, setSaved] = useState(null);
  const { isSaved } = useSaved();

  useEffect(() => {
    api('/saved').then(setSaved);
  }, []);

  if (saved === null) return <div className="page container loading">Loading saved items...</div>;

  // Un-hearting something here should drop it from the list straight away
  // rather than leaving a saved item that says it isn't saved.
  const products = saved.products.filter((p) => isSaved('product', p.id));
  const plants = saved.plants.filter((p) => isSaved('plant', p.id));

  return (
    <div className="page container">
      <h1 className="page-title">Saved</h1>

      {products.length === 0 && plants.length === 0 && (
        <div className="empty-state">
          <span className="empty-state__icon">♡</span>
          Nothing saved yet. Tap the heart on a listing or a plant to keep it here.
        </div>
      )}

      {products.length > 0 && (
        <>
          <h2 style={{ fontSize: 19, marginTop: 8 }}>Listings</h2>
          <div className="grid">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </>
      )}

      {plants.length > 0 && (
        <>
          <h2 style={{ fontSize: 19, marginTop: 24 }}>Plants</h2>
          <div className="card" style={{ padding: 8 }}>
            {plants.map((plant) => (
              <div
                key={plant.id}
                className="flex-between"
                style={{ padding: '10px 8px', borderBottom: '1px solid var(--sand-dark)' }}
              >
                <Link to={`/plants/${plant.id}`}>
                  <strong>{plant.name}</strong>
                  <span style={{ fontSize: 13, color: 'var(--ink-500)', marginLeft: 8 }}>
                    {plant.type?.replace('_', ' ')}
                  </span>
                </Link>
                <SaveButton type="plant" id={plant.id} returnTo="/saved" />
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
