import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useCart } from '../context/CartContext';
import { useAuth } from '../context/AuthContext';
import { api, ApiError } from '../api/client';

export default function Cart() {
  const { items, updateQuantity, removeItem, clear } = useCart();
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
      setError(err instanceof ApiError ? err.message : 'Could not request those.');
    } finally {
      setBusy(false);
    }
  };

  if (placedOrder) {
    return (
      <div className="page container">
        <div className="alert alert--success">
          Swap #{placedOrder.id} requested. The growers offering these have been told;
          you can follow it on your <Link to="/orders">swaps page</Link>.
        </div>
      </div>
    );
  }

  if (items.length === 0) {
    return (
      <div className="page container">
        <h1 className="page-title">Your swap list</h1>
        <div className="empty-state">
          Nothing on your list yet. <Link to="/">See what people are offering</Link>.
        </div>
      </div>
    );
  }

  return (
    <div className="page container">
      <h1 className="page-title">Your swap list</h1>

      {error && <div className="alert alert--error">{error}</div>}

      <div className="card" style={{ padding: 16 }}>
        {items.map((item) => (
          <CartLine
            key={item.product.id}
            item={item}
            updateQuantity={updateQuantity}
            removeItem={removeItem}
          />
        ))}

        <div className="flex-between" style={{ marginTop: 16 }}>
          {user ? (
            <button className="btn" onClick={checkout} disabled={busy}>
              {busy ? 'Requesting...' : 'Request these'}
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

/**
 * One row of the swap list. The quantity field keeps its own draft string so it can be
 * empty while the user retypes a number: committing "" straight to the cart
 * would read as Number('') === 0, which updateQuantity treats as "remove",
 * and the line would vanish mid-edit. Deleting a line is what Remove is for.
 */
function CartLine({ item, updateQuantity, removeItem }) {
  // null means "show the committed quantity"; a string means the user is
  // part-way through typing one.
  const [draft, setDraft] = useState(null);
  const shown = draft ?? String(item.quantity);

  return (
    <div
      className="flex-between"
      style={{ padding: '12px 0', borderBottom: '1px solid var(--sand-dark)' }}
    >
      <div>
        <Link to={`/products/${item.product.id}`}>
          <strong>{item.product.title}</strong>
        </Link>
        <div style={{ fontSize: 13, color: 'var(--ink-500)' }}>
        </div>
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
        <input
          type="number"
          min={1}
          max={item.product.stock}
          value={shown}
          onChange={(e) => {
            const raw = e.target.value;
            setDraft(raw);
            if (raw !== '') updateQuantity(item.product.id, Number(raw));
          }}
          onBlur={() => setDraft(null)}
          style={{ width: 60, padding: '7px 9px', borderRadius: 8, border: '1.5px solid var(--sand-dark)' }}
        />
        <button className="btn btn--ghost" onClick={() => removeItem(item.product.id)}>
          Remove
        </button>
      </div>
    </div>
  );
}
