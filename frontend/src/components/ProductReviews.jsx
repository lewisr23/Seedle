import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, ApiError } from '../api/client';
import { useAuth } from '../context/AuthContext';
import Avatar from './Avatar';
import Stars from './Stars';
import { timeAgo } from '../utils/time';

export default function ProductReviews({ product, onReviewed }) {
  const { user } = useAuth();
  const [reviews, setReviews] = useState(null);
  const [rating, setRating] = useState(0);
  const [body, setBody] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const load = () => api(`/products/${product.id}/reviews`).then((res) => setReviews(res.data));

  useEffect(() => {
    load();
  }, [product.id]);

  const mine = reviews?.find((r) => r.is_mine);

  const submit = async (e) => {
    e.preventDefault();
    if (!rating) {
      setError('Pick a star rating first.');
      return;
    }
    setBusy(true);
    setError('');
    try {
      await api(`/products/${product.id}/reviews`, { method: 'POST', body: { rating, body } });
      setRating(0);
      setBody('');
      load();
      onReviewed?.();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not post your review.');
    } finally {
      setBusy(false);
    }
  };

  const remove = async (id) => {
    await api(`/products/${product.id}/reviews/${id}`, { method: 'DELETE' });
    load();
    onReviewed?.();
  };

  return (
    <div className="section" style={{ marginTop: 30 }}>
      <div className="flex-between" style={{ marginBottom: 14 }}>
        <h2 className="section-title" style={{ marginBottom: 0 }}>
          Reviews
        </h2>
        {product.reviews_count > 0 && (
          <span style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 14 }}>
            <Stars value={product.rating_average} size={16} />
            <strong>{product.rating_average}</strong>
            <span style={{ color: 'var(--ink-500)' }}>({product.reviews_count})</span>
          </span>
        )}
      </div>

      {user && !mine && (
        <form className="card" onSubmit={submit} style={{ padding: 18, marginBottom: 18 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 10 }}>
            <span style={{ fontSize: 14, fontWeight: 700 }}>Your rating</span>
            <Stars value={rating} size={22} onChange={setRating} />
          </div>
          <textarea
            placeholder="How did it grow? Was it as described?"
            value={body}
            onChange={(e) => setBody(e.target.value)}
            style={{
              width: '100%',
              padding: 12,
              borderRadius: 12,
              border: '1.5px solid var(--sand-dark)',
              fontFamily: 'inherit',
              fontSize: 14.5,
            }}
          />
          {error && <div className="alert alert--error" style={{ marginTop: 10 }}>{error}</div>}
          <div style={{ marginTop: 10 }}>
            <button className="btn btn--sm" type="submit" disabled={busy}>
              {busy ? 'Posting...' : 'Post review'}
            </button>
            <span style={{ fontSize: 12.5, color: 'var(--ink-300)', marginLeft: 12 }}>
              Only buyers of this item can review it.
            </span>
          </div>
        </form>
      )}

      {!user && (
        <p style={{ fontSize: 14, color: 'var(--ink-500)' }}>
          <Link to="/login">Log in</Link> to leave a review if you've bought this.
        </p>
      )}

      {reviews === null && <div className="loading">Loading reviews...</div>}
      {reviews?.length === 0 && (
        <div className="empty-state" style={{ padding: '24px 0' }}>
          No reviews yet.
        </div>
      )}

      {reviews?.map((review) => (
        <div className="card review" key={review.id}>
          <Avatar name={review.user?.username} size={34} />
          <div style={{ flex: 1 }}>
            <div className="review__head">
              <Link to={`/u/${review.user?.username}`} className="review__author">
                {review.user?.username}
              </Link>
              <Stars value={review.rating} />
              <span className="review__time">{timeAgo(review.created_at)}</span>
              {review.is_mine && (
                <button className="icon-btn" onClick={() => remove(review.id)}>
                  Delete
                </button>
              )}
            </div>
            {review.body && <p className="review__body">{review.body}</p>}
          </div>
        </div>
      ))}
    </div>
  );
}
