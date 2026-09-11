import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../api/client';
import PostCard from '../components/PostCard';

export default function PostDetail() {
  const { id } = useParams();
  const [post, setPost] = useState(null);
  const [notFound, setNotFound] = useState(false);

  const load = () =>
    api(`/posts/${id}`)
      .then((res) => setPost(res.data))
      .catch(() => setNotFound(true));

  useEffect(() => {
    setPost(null);
    setNotFound(false);
    load();
  }, [id]);

  if (notFound) {
    return (
      <div className="page container">
        <div className="empty-state">
          <span className="empty-state__icon">🍂</span>
          That post isn't around any more.
          <div style={{ marginTop: 12 }}>
            <Link to="/feed" className="btn btn--sm">
              Back to the community
            </Link>
          </div>
        </div>
      </div>
    );
  }

  if (post === null) return <div className="page container loading">Loading post...</div>;

  return (
    <div className="page container" style={{ maxWidth: 760 }}>
      <Link to="/feed" style={{ fontSize: 13, color: 'var(--ink-500)' }}>
        ← Community
      </Link>
      <div style={{ marginTop: 14 }}>
        <PostCard post={post} onChange={load} defaultCommentsOpen />
      </div>
    </div>
  );
}
