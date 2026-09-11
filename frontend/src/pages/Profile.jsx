import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import ProductCard from '../components/ProductCard';
import PostCard from '../components/PostCard';
import Avatar from '../components/Avatar';
import { useCart } from '../context/CartContext';

export default function Profile() {
  const { username } = useParams();
  const { user: me } = useAuth();
  const { addItem } = useCart();
  const [profile, setProfile] = useState(null);
  const [products, setProducts] = useState([]);
  const [posts, setPosts] = useState(null);
  const [busy, setBusy] = useState(false);

  const load = () => api(`/users/${username}`).then((res) => setProfile(res.data));
  const loadPosts = () => api(`/users/${username}/posts`).then((res) => setPosts(res.data));

  useEffect(() => {
    load();
    loadPosts();
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
      <div className="card" style={{ padding: 24, display: 'flex', gap: 20, alignItems: 'flex-start' }}>
        <Avatar name={profile.username} size={64} />
        <div style={{ flex: 1 }}>
          <div className="flex-between">
            <div>
              <h1 className="page-title" style={{ marginBottom: 2 }}>
                {profile.name}
              </h1>
              <p style={{ color: 'var(--ink-500)', margin: 0 }}>@{profile.username}</p>
            </div>
            {me && !isMe && (
              <button className="btn" onClick={toggleFollow} disabled={busy}>
                {profile.is_following ? 'Unfollow' : 'Follow'}
              </button>
            )}
          </div>
          {profile.bio && <p style={{ marginTop: 12 }}>{profile.bio}</p>}
          <div style={{ display: 'flex', gap: 16, marginTop: 12, fontSize: 13, color: 'var(--ink-500)', flexWrap: 'wrap' }}>
            {profile.location && <span>📍 {profile.location}</span>}
            {profile.hardiness_zone && <span>🌡️ Zone {profile.hardiness_zone}</span>}
            <span>{profile.followers_count ?? 0} followers</span>
            <span>{profile.following_count ?? 0} following</span>
          </div>
        </div>
      </div>

      {products.length > 0 && (
        <div className="section" style={{ marginTop: 28 }}>
          <h2 className="section-title">Listings</h2>
          <div className="grid">
            {products.map((p) => (
              <ProductCard key={p.id} product={p} onAddToCart={(prod) => addItem(prod, 1)} />
            ))}
          </div>
        </div>
      )}

      <div className="section" style={{ marginTop: 28 }}>
        <h2 className="section-title">Posts</h2>
        {posts === null && <div className="loading">Loading posts...</div>}
        {posts?.length === 0 && <div className="empty-state">No posts yet.</div>}
        {posts?.map((post) => (
          <PostCard key={post.id} post={post} onChange={loadPosts} />
        ))}
      </div>
    </div>
  );
}
