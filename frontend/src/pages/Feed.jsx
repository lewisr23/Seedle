import { useEffect, useState } from 'react';
import { api, ApiError } from '../api/client';
import { useAuth } from '../context/AuthContext';
import PostCard from '../components/PostCard';

const TYPES = [
  { value: 'update', label: 'Update' },
  { value: 'question', label: 'Question' },
  { value: 'tip', label: 'Tip' },
];

export default function Feed() {
  const { user } = useAuth();
  const [tab, setTab] = useState(user ? 'following' : 'explore');
  const [posts, setPosts] = useState(null);
  const [body, setBody] = useState('');
  const [type, setType] = useState('update');
  const [error, setError] = useState('');

  const load = () => {
    setPosts(null);
    const endpoint = tab === 'following' && user ? '/feed' : '/posts';
    api(endpoint).then((res) => setPosts(res.data));
  };

  useEffect(load, [tab, user]);

  const submitPost = async (e) => {
    e.preventDefault();
    if (!body.trim()) return;
    setError('');
    try {
      await api('/posts', { method: 'POST', body: { type, body } });
      setBody('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not post.');
    }
  };

  return (
    <div className="page container">
      <h1 className="page-title">Community</h1>
      <p className="page-subtitle">Updates, questions and tips from fellow gardeners.</p>

      {user && (
        <div className="card section" style={{ padding: 16 }}>
          {error && <div className="alert alert--error">{error}</div>}
          <form onSubmit={submitPost}>
            <textarea
              placeholder="Share an update, ask a question, or post a tip..."
              value={body}
              onChange={(e) => setBody(e.target.value)}
              style={{ width: '100%', padding: 10, borderRadius: 8, border: '1px solid #ede7db' }}
            />
            <div className="flex-between" style={{ marginTop: 10 }}>
              <select value={type} onChange={(e) => setType(e.target.value)}>
                {TYPES.map((t) => (
                  <option key={t.value} value={t.value}>
                    {t.label}
                  </option>
                ))}
              </select>
              <button className="btn" type="submit">
                Post
              </button>
            </div>
          </form>
        </div>
      )}

      {user && (
        <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
          <button
            className={`btn ${tab === 'following' ? '' : 'btn--outline'}`}
            onClick={() => setTab('following')}
          >
            Following
          </button>
          <button className={`btn ${tab === 'explore' ? '' : 'btn--outline'}`} onClick={() => setTab('explore')}>
            Explore
          </button>
        </div>
      )}

      {posts === null && <div className="loading">Loading posts...</div>}
      {posts?.length === 0 && <div className="empty-state">Nothing here yet.</div>}
      {posts?.map((post) => (
        <PostCard key={post.id} post={post} />
      ))}
    </div>
  );
}
