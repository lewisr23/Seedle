import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, ApiError } from '../api/client';

const CATEGORY_LABELS = {
  seed: 'Seeds',
  live_plant: 'Live Plant',
  tool: 'Tool',
  fertilizer: 'Fertilizer',
  other: 'Other',
};

const STATUS_COLORS = {
  pending: 'var(--amber-600)',
  processing: 'var(--terracotta-600)',
  completed: 'var(--green-700)',
  cancelled: 'var(--red-500)',
};

function ListingRow({ product, onChanged }) {
  const [editing, setEditing] = useState(false);
  const [price, setPrice] = useState((product.price_pence / 100).toFixed(2));
  const [stock, setStock] = useState(String(product.stock));
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const save = async () => {
    setBusy(true);
    setError('');
    try {
      await api(`/products/${product.id}`, {
        method: 'PUT',
        body: { price_pence: Math.round(Number(price) * 100), stock: Number(stock) },
      });
      setEditing(false);
      onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not save.');
    } finally {
      setBusy(false);
    }
  };

  const toggleActive = async () => {
    setBusy(true);
    try {
      await api(`/products/${product.id}`, { method: 'PUT', body: { is_active: !product.is_active } });
      onChanged();
    } finally {
      setBusy(false);
    }
  };

  const remove = async () => {
    if (!window.confirm(`Delete "${product.title}"? This can't be undone.`)) return;
    setBusy(true);
    try {
      await api(`/products/${product.id}`, { method: 'DELETE' });
      onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not delete.');
      setBusy(false);
    }
  };

  return (
    <div className={`listing-row${product.is_active ? '' : ' listing-row--paused'}`}>
      <div className="listing-row__main">
        <Link to={`/products/${product.id}`} className="listing-row__title">
          {product.title}
        </Link>
        <div className="listing-row__meta">
          <span className="tag">{CATEGORY_LABELS[product.category] || product.category}</span>
          {!product.is_active && <span className="tag">Paused</span>}
          {product.stock === 0 && <span className="tag">Out of stock</span>}
          {product.plant && <span className="tag">🌿 {product.plant.name}</span>}
        </div>
        {error && <span className="error-text">{error}</span>}
      </div>

      {editing ? (
        <div className="listing-row__actions">
          <label className="inline-field">
            £
            <input type="number" step="0.01" min="0.01" value={price} onChange={(e) => setPrice(e.target.value)} />
          </label>
          <label className="inline-field">
            Stock
            <input type="number" min="0" value={stock} onChange={(e) => setStock(e.target.value)} />
          </label>
          <button className="btn btn--sm" onClick={save} disabled={busy}>
            Save
          </button>
          <button className="btn btn--ghost btn--sm" onClick={() => setEditing(false)} disabled={busy}>
            Cancel
          </button>
        </div>
      ) : (
        <div className="listing-row__actions">
          <span className="listing-row__price">£{product.price_pounds.toFixed(2)}</span>
          <span className="listing-row__stock">{product.stock} in stock</span>
          <button className="btn btn--outline btn--sm" onClick={() => setEditing(true)}>
            Edit
          </button>
          <button className="btn btn--ghost btn--sm" onClick={toggleActive} disabled={busy}>
            {product.is_active ? 'Pause' : 'Resume'}
          </button>
          <button className="btn btn--ghost btn--sm" onClick={remove} disabled={busy}>
            Delete
          </button>
        </div>
      )}
    </div>
  );
}

export default function Dashboard() {
  const [tab, setTab] = useState('listings');
  const [listings, setListings] = useState(null);
  const [sales, setSales] = useState(null);

  const loadListings = () => api('/my-listings').then((res) => setListings(res.data));
  const loadSales = () => api('/sales').then((res) => setSales(res.data));

  useEffect(() => {
    loadListings();
    loadSales();
  }, []);

  const activeCount = listings?.filter((l) => l.is_active).length ?? 0;
  const revenuePence =
    sales?.reduce(
      (sum, order) => sum + order.items.reduce((s, i) => s + i.unit_price_pence * i.quantity, 0),
      0
    ) ?? 0;

  return (
    <div className="page container">
      <span className="eyebrow">Your shop</span>
      <h1 className="page-title">Seller dashboard</h1>
      <p className="page-subtitle">Manage what you're selling and see what's sold.</p>

      <div className="stat-row">
        <div className="stat-card">
          <strong>{listings?.length ?? ': '}</strong>
          <span>Listings</span>
        </div>
        <div className="stat-card">
          <strong>{listings ? activeCount : ': '}</strong>
          <span>Active</span>
        </div>
        <div className="stat-card">
          <strong>{sales?.length ?? ': '}</strong>
          <span>Orders</span>
        </div>
        <div className="stat-card">
          <strong>{sales ? `£${(revenuePence / 100).toFixed(2)}` : ': '}</strong>
          <span>Revenue</span>
        </div>
      </div>

      <div style={{ display: 'flex', gap: 8, margin: '24px 0 20px' }}>
        <button
          className={`category-pill${tab === 'listings' ? ' active' : ''}`}
          onClick={() => setTab('listings')}
        >
          My listings
        </button>
        <button className={`category-pill${tab === 'sales' ? ' active' : ''}`} onClick={() => setTab('sales')}>
          Sales
        </button>
        <Link to="/sell" className="btn btn--sm" style={{ marginLeft: 'auto' }}>
          + New listing
        </Link>
      </div>

      {tab === 'listings' && (
        <>
          {listings === null && <div className="loading">Loading your listings...</div>}
          {listings?.length === 0 && (
            <div className="empty-state">
              <span className="empty-state__icon">🪴</span>
              You haven't listed anything yet.
              <div style={{ marginTop: 12 }}>
                <Link to="/sell" className="btn btn--sm">
                  List your first item
                </Link>
              </div>
            </div>
          )}
          {listings?.map((product) => (
            <ListingRow key={product.id} product={product} onChanged={loadListings} />
          ))}
        </>
      )}

      {tab === 'sales' && (
        <>
          {sales === null && <div className="loading">Loading your sales...</div>}
          {sales?.length === 0 && (
            <div className="empty-state">
              <span className="empty-state__icon">📦</span>
              No sales yet. They'll show up here as soon as someone buys from you.
            </div>
          )}
          {sales?.map((order) => (
            <div className="card" key={order.id} style={{ padding: 18, marginBottom: 14 }}>
              <div className="flex-between">
                <strong>Order #{order.id}</strong>
                <span
                  style={{
                    color: STATUS_COLORS[order.status],
                    fontWeight: 700,
                    textTransform: 'capitalize',
                    fontSize: 13.5,
                  }}
                >
                  ● {order.status}
                </span>
              </div>
              <p style={{ fontSize: 13, color: 'var(--ink-500)', margin: '4px 0 10px' }}>
                {new Date(order.created_at).toLocaleString()}
                {order.buyer && (
                  <>
                    {' · bought by '}
                    <Link to={`/u/${order.buyer.username}`}>{order.buyer.username}</Link>
                  </>
                )}
              </p>
              {order.items?.map((item) => (
                <div key={item.id} className="flex-between" style={{ fontSize: 14, padding: '3px 0' }}>
                  <span>
                    {item.quantity} × {item.product?.title}
                  </span>
                  <span style={{ fontWeight: 600 }}>
                    £{((item.unit_price_pence * item.quantity) / 100).toFixed(2)}
                  </span>
                </div>
              ))}
            </div>
          ))}
        </>
      )}
    </div>
  );
}
