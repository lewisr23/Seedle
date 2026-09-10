import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import ProductCard from '../components/ProductCard';
import { useCart } from '../context/CartContext';

export default function Profile() {
  const { username } = useParams();
  const { user: me } = useAuth();
  const { addItem } = useCart();
  const [profile, setProfile] = useState(null);
  const [products, setProducts] = useState([]);
  const [busy, setBusy] = useState(false);

  const load = () => api(`/users/${username}`).then((res) => setProfile(res.data));

  useEffect(() => {
    load();
  }, [username]);

  useEffect(() => {
    if (profile) {
      api('/products', { params: { seller_id: profile.id, per_page: 12 } }).then((res) => setProducts(res.data));
    }
  }, [profile?.id]);

  const toggleFollow = async () => {
    setBusy(true);
    try {
      await api(`/users/${username}/follow`, { method: profile.is_following ? 'DELETE' : 'POST' });
      load();
    } finally {
      setBusy(false);
    }
  };

  if (!profile) return <div className="page container loading">Loading...</div>;

  const isMe = me?.username === profile.username;

  return (
    <div className="page container">
      <div className="card" style={{ padding: 20 }}>
        <div className="flex-between">
          <div>
            <h1 className="page-title">{profile.name}</h1>
            <p style={{ color: '#4f5a52', margin: 0 }}>@{profile.username}</p>
          </div>
          {me && !isMe && (
            <button className="btn" onClick={toggleFollow} disabled={busy}>
              {profile.is_following ? 'Unfollow' : 'Follow'}
            </button>
          )}
        </div>
        {profile.bio && <p style={{ marginTop: 12 }}>{profile.bio}</p>}
        <div style={{ display: 'flex', gap: 16, marginTop: 10, fontSize: 13, color: '#4f5a52' }}>
          {profile.location && <span>📍 {profile.location}</span>}
          {profile.hardiness_zone && <span>🌡️ Zone {profile.hardiness_zone}</span>}
          <span>{profile.followers_count ?? 0} followers</span>
          <span>{profile.following_count ?? 0} following</span>
        </div>
      </div>

      {products.length > 0 && (
        <div className="section" style={{ marginTop: 24 }}>
          <h2 className="section-title">Listings</h2>
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
