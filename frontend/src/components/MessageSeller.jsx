import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, ApiError } from '../api/client';
import { useAuth } from '../context/AuthContext';

/**
 * "Ask the seller a question" on a listing. Posting opens (or continues) the
 * one conversation between this buyer and this listing, then hands over to the
 * thread view, so the composer here only ever sends the first message.
 */
export default function MessageSeller({ product }) {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const [body, setBody] = useState('');
  const [error, setError] = useState('');
  const [sending, setSending] = useState(false);

  // Nothing to ask yourself, and signed-out visitors get pointed at login.
  if (user && user.id === product.seller?.id) return null;

  if (!user) {
    return (
      <button
        className="btn btn--ghost"
        onClick={() => navigate('/login', { state: { from: { pathname: `/products/${product.id}` } } })}
      >
        Log in to message seller
      </button>
    );
  }

  if (!open) {
    return (
      <button className="btn btn--ghost" onClick={() => setOpen(true)}>
        Message seller
      </button>
    );
  }

  const send = async (event) => {
    event.preventDefault();
    const trimmed = body.trim();
    if (!trimmed) return;

    setError('');
    setSending(true);
    try {
      const res = await api('/conversations', {
        method: 'POST',
        body: { product_id: product.id, body: trimmed },
      });
      navigate(`/messages/${res.data.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not send that message.');
      setSending(false);
    }
  };

  return (
    <form onSubmit={send} style={{ marginTop: 14, maxWidth: 420 }}>
      <label htmlFor="message-seller" style={{ fontSize: 13, fontWeight: 600 }}>
        Message {product.seller?.username}
      </label>
      <textarea
        id="message-seller"
        value={body}
        onChange={(e) => setBody(e.target.value)}
        placeholder="Is this still available?"
        rows={3}
        maxLength={2000}
        style={{
          width: '100%',
          marginTop: 6,
          padding: '10px 12px',
          borderRadius: 10,
          border: '1.5px solid var(--sand-dark)',
          fontFamily: 'inherit',
          resize: 'vertical',
        }}
      />
      {error && <div className="alert alert--error" style={{ marginTop: 8 }}>{error}</div>}
      <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
        <button className="btn" type="submit" disabled={sending || !body.trim()}>
          {sending ? 'Sending...' : 'Send message'}
        </button>
        <button className="btn btn--ghost" type="button" onClick={() => setOpen(false)}>
          Cancel
        </button>
      </div>
    </form>
  );
}
