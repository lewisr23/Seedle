import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { api } from '../api/client';
import { useCart } from '../context/CartContext';

export default function ProductDetail() {
  const { id } = useParams();
  const { addItem } = useCart();
  const [product, setProduct] = useState(null);
  const [loading, setLoading] = useState(true);
  const [quantity, setQuantity] = useState(1);
  const [added, setAdded] = useState(false);

  useEffect(() => {
    setLoading(true);
    api(`/products/${id}`)
      .then((res) => setProduct(res.data))
      .finally(() => setLoading(false));
  }, [id]);

  if (loading) return <div className="page container loading">Loading...</div>;
  if (!product) return <div className="page container empty-state">Product not found.</div>;

  return (
    <div className="page container">
      <div className="card" style={{ padding: 24, maxWidth: 640 }}>
        <span className="tag">{product.category}</span>
        <h1 className="page-title" style={{ marginTop: 10 }}>
          {product.title}
        </h1>
        <p style={{ color: '#4f5a52' }}>{product.description}</p>

        <p style={{ fontSize: 24, fontWeight: 700, color: '#2d5a3f' }}>
          £{product.price_pounds?.toFixed(2)}
        </p>
        <p style={{ fontSize: 14 }}>
          {product.stock > 0 ? `${product.stock} in stock` : 'Out of stock'} · sold by{' '}
          <Link to={`/u/${product.seller?.username}`}>{product.seller?.username}</Link>
        </p>

        {product.plant && (
          <div className="card" style={{ padding: 14, marginTop: 14, background: '#e6f2ea' }}>
            <strong>{product.plant.name} care</strong>
            <p style={{ fontSize: 13, margin: '6px 0 0' }}>
              Sun: {product.plant.sun_requirement.replace('_', ' ')} · Water: {product.plant.water_needs} · Zones{' '}
              {product.plant.min_zone}-{product.plant.max_zone}
            </p>
          </div>
        )}

        {product.stock > 0 && (
          <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginTop: 18 }}>
            <input
              type="number"
              min={1}
              max={product.stock}
              value={quantity}
              onChange={(e) => setQuantity(Math.max(1, Math.min(product.stock, Number(e.target.value))))}
              style={{ width: 70, padding: '9px 12px', borderRadius: 10, border: '1px solid #ede7db' }}
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
            {added && <span className="alert alert--success" style={{ padding: '6px 10px' }}>Added!</span>}
          </div>
        )}
      </div>
    </div>
  );
}
