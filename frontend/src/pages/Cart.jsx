import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useCart } from '../context/CartContext';
import { useAuth } from '../context/AuthContext';
import { api, ApiError } from '../api/client';

export default function Cart() {
  const { items, updateQuantity, removeItem, clear, totalPence } = useCart();
  const { user } = useAuth();
  const navigate = useNavigate();
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [placedOrder, setPlacedOrder] = useState(null);

  const checkout = async () => {
    setError('');
    setBusy(true);
    try {
      const res = await api('/checkout', {
        method: 'POST',
        body: { items: items.map((i) => ({ product_id: i.product.id, quantity: i.quantity })) },
      });
      setPlacedOrder(res.data);
      clear();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Checkout failed.');
    } finally {
      setBusy(false);
    }
  };

  if (placedOrder) {
    return (
      <div className="page container">
        <div className="alert alert--success">
          Order #{placedOrder.id} placed — total £{placedOrder.total_pounds.toFixed(2)}. It's now being processed;
          you can check its status any time on your <Link to="/orders">orders page</Link>.
        </div>
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="page container">
        <h1 className="page-title">Your cart</h1>
        <div className="empty-state">
          Your cart is empty. <Link to="/">Browse the marketplace</Link>.
        </div>
      </div>
    );
  }

  return (
    <div className="page container">
      <h1 className="page-title">Your cart</h1>

      {error && <div className="alert alert--error">{error}</div>}

      <div className="card" style={{ padding: 16 }}>
        {items.map((item) => (
          <div
            key={item.product.id}
            className="flex-between"
            style={{ padding: '10px 0', borderBottom: '1px solid #ede7db' }}
          >
            <div>
              <Link to={`/products/${item.product.id}`}>
                <strong>{item.product.title}</strong>
              </Link>
              <div style={{ fontSize: 13, color: '#4f5a52' }}>
                £{item.product.price_pounds.toFixed(2)} each
              </div>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              <input
                type="number"
                min={1}
                max={item.product.stock}
                value={item.quantity}
                onChange={(e) => updateQuantity(item.product.id, Number(e.target.value))}
                style={{ width: 60, padding: '6px 8px', borderRadius: 8, border: '1px solid #ede7db' }}
              />
              <span style={{ width: 70, textAlign: 'right' }}>
                £{((item.product.price_pence * item.quantity) / 100).toFixed(2)}
              </span>
              <button className="btn btn--ghost" onClick={() => removeItem(item.product.id)}>
                Remove
              </button>
            </div>
          </div>
        ))}

        <div className="flex-between" style={{ marginTop: 16 }}>
          <strong>Total: £{(totalPence / 100).toFixed(2)}</strong>
          {user ? (
            <button className="btn" onClick={checkout} disabled={busy}>
              {busy ? 'Placing order...' : 'Checkout'}
            </button>
          ) : (
            <button className="btn" onClick={() => navigate('/login', { state: { from: { pathname: '/cart' } } })}>
              Log in to checkout
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
