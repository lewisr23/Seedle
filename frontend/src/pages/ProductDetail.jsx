import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api/client';
import { useCart } from '../context/CartContext';
import MessageSeller from '../components/MessageSeller';
import SaveButton from '../components/SaveButton';
import ProductReviews from '../components/ProductReviews';
import Stars from '../components/Stars';

const CATEGORY_META = {
  seed: { label: 'Seeds', icon: '🌱' },
  live_plant: { label: 'Live Plant', icon: '🪴' },
  tool: { label: 'Tool', icon: '🛠️' },
  fertilizer: { label: 'Fertilizer', icon: '🌾' },
  other: { label: 'Other', icon: '🧺' },
};

export default function ProductDetail() {
  const { id } = useParams();
  const { addItem } = useCart();
  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  const [quantity, setQuantity] = useState(1);
  const [added, setAdded] = useState(false);

  const load = () => api(`/products/${id}`).then((res) => setProduct(res.data));

  useEffect(() => {
    setLoading(true);
    load().finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div className="page container loading">Loading...</div>;
  if (!product) return <div className="page container empty-state">Product not found.</div>;

  const meta = CATEGORY_META[product.category] || CATEGORY_META.other;

  return (
    <div className="page container">
      <div className="card" style={{ maxWidth: 640, overflow: 'hidden' }}>
        <div className={`product-card__media media-${product.category}`} style={{ height: 160, fontSize: 56 }}>
          {product.images?.[0] ? (
            <img
              src={product.images[0]}
              alt={product.title}
              style={{ width: '100%', height: '100%', objectFit: 'cover' }}
            />
          ) : (
            meta.icon
          )}
        </div>
        <div style={{ padding: 24 }}>
          <span className="tag">{meta.label}</span>
          <h1 className="page-title" style={{ marginTop: 10 }}>
            {product.title}
          </h1>
          {product.reviews_count > 0 && (
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
              <Stars value={product.rating_average} size={16} />
              <span style={{ fontSize: 13.5, color: 'var(--ink-500)' }}>
                {product.rating_average} · {product.reviews_count} review
                {product.reviews_count === 1 ? '' : 's'}
              </span>
            </div>
          )}
          {product.images?.length > 1 && (
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 12 }}>
              {product.images.slice(1).map((src) => (
                <img
                  key={src}
                  src={src}
                  alt={product.title}
                  loading="lazy"
                  style={{ width: 88, height: 88, objectFit: 'cover', borderRadius: 8 }}
                />
              ))}
            </div>
          )}
          <p style={{ color: 'var(--ink-500)' }}>{product.description}</p>

          <p style={{ fontSize: 26, fontWeight: 800, color: 'var(--green-700)', fontFamily: 'var(--font-heading)' }}>
            £{product.price_pounds?.toFixed(2)}
          </p>
          <p style={{ fontSize: 14 }}>
            {product.stock > 0 ? `${product.stock} in stock` : 'Out of stock'} · sold by{' '}
            <Link to={`/u/${product.seller?.username}`}>{product.seller?.username}</Link>
          </p>

          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 12 }}>
            <SaveButton type="product" id={product.id} returnTo={`/products/${product.id}`} size={24} />
            <MessageSeller product={product} />
          </div>

          {product.plant && (
            <div className="card" style={{ padding: 16, marginTop: 16, background: 'var(--green-50)' }}>
              <strong>{product.plant.name} care</strong>
              <p style={{ fontSize: 13, margin: '6px 0 0', color: 'var(--ink-500)' }}>
                Sun: {product.plant.sun_requirement.replace('_', ' ')} · Water: {product.plant.water_needs} · Zones{' '}
                {product.plant.min_zone}-{product.plant.max_zone}
              </p>
            </div>
          )}

          {product.stock > 0 && (
            <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginTop: 20 }}>
              <input
                type="number"
                min={1}
                max={product.stock}
                value={quantity}
                onChange={(e) => setQuantity(Math.max(1, Math.min(product.stock, Number(e.target.value))))}
                style={{ width: 70, padding: '10px 12px', borderRadius: 10, border: '1.5px solid var(--sand-dark)' }}
              />
              <button
                className="btn"
                onClick={() => {
                  addItem(product, quantity);
                  setAdded(true);
                  setTimeout(() => setAdded(false), 1500);
                }}
              >
                Add to cart
              </button>
              {added && <span className="alert alert--success" style={{ padding: '7px 12px', margin: 0 }}>Added!</span>}
            </div>
          )}
        </div>
      </div>

      <div style={{ maxWidth: 640 }}>
        <ProductReviews product={product} onReviewed={load} />
      </div>
    </div>
  );
}
