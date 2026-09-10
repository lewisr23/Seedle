import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';

const TYPE_LABELS = { update: 'Update', question: 'Question', tip: 'Tip' };

export default function PostCard({ post }) {
  const { user } = useAuth();
  const [liked, setLiked] = useState(post.liked_by_me);
  const [likesCount, setLikesCount] = useState(post.likes_count || 0);
  const [showComments, setShowComments] = useState(false);
  const [comments, setComments] = useState(null);
  const [commentBody, setCommentBody] = useState('');
  const [busy, setBusy] = useState(false);

  const toggleLike = async () => {
    if (!user || busy) return;
    setBusy(true);
    try {
      if (liked) {
        await api(`/posts/${post.id}/like`, { method: 'DELETE' });
        setLiked(false);
        setLikesCount((c) => c - 1);
      } else {
        await api(`/posts/${post.id}/like`, { method: 'POST' });
        setLiked(true);
        setLikesCount((c) => c + 1);
      }
    } catch {
      // Silently ignore — not critical enough to interrupt the feed.
    } finally {
      setBusy(false);
    }
  };

  const loadComments = async () => {
    setShowComments((v) => !v);
    if (comments === null) {
      const res = await api(`/posts/${post.id}/comments`);
      setComments(res.data);
    }
  };

  const submitComment = async (e) => {
    e.preventDefault();
    if (!commentBody.trim()) return;
    const res = await api(`/posts/${post.id}/comments`, { method: 'POST', body: { body: commentBody } });
    setComments((prev) => [...(prev || []), res.data]);
    setCommentBody('');
  };

  return (
    <div className="post-card">
      <div className="post-card__head">
        <Link to={`/u/${post.user?.username}`} className="post-card__author">
          {post.user?.username}
        </Link>
        <span className="post-card__type">{TYPE_LABELS[post.type] || post.type}</span>
      </div>
      {post.plant && <span className="tag" style={{ marginBottom: 8, display: 'inline-block' }}>{post.plant.name}</span>}
      <p className="post-card__body">{post.body}</p>
      <div className="post-card__actions">
        <button className={liked ? 'liked' : ''} onClick={toggleLike} disabled={!user}>
          {liked ? '♥' : '♡'} {likesCount}
        </button>
        <button onClick={loadComments}>💬 {post.comments_count ?? ''} Comments</button>
      </div>

      {showComments && (
        <div className="post-card__comments">
          {comments === null && <div className="loading">Loading comments...</div>}
          {comments?.map((c) => (
            <div className="comment" key={c.id}>
              <strong>{c.user?.username}</strong>
              {c.body}
            </div>
          ))}
          {comments?.length === 0 && <div className="empty-state">No comments yet.</div>}
          {user && (
            <form onSubmit={submitComment} style={{ display: 'flex', gap: 8, marginTop: 10 }}>
              <input
                type="text"
                placeholder="Add a comment..."
                value={commentBody}
                onChange={(e) => setCommentBody(e.target.value)}
                style={{ flex: 1, padding: '8px 10px', borderRadius: 8, border: '1px solid #ede7db' }}
              />
              <button className="btn" type="submit">
                Post
              </button>
            </form>
          )}
        </div>
      )}
    </div>
  );
}
