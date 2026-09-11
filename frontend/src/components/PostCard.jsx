import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import Avatar from './Avatar';
import { timeAgo } from '../utils/time';

const TYPE_LABELS = { update: 'Update', question: 'Question', tip: 'Tip' };

export default function PostCard({ post, onChange, defaultCommentsOpen = false }) {
  const { user } = useAuth();
  const [liked, setLiked] = useState(post.liked_by_me);
  const [likesCount, setLikesCount] = useState(post.likes_count || 0);
  const [pinned, setPinned] = useState(post.pinned);
  const [showComments, setShowComments] = useState(defaultCommentsOpen);
  const [comments, setComments] = useState(null);
  const [commentBody, setCommentBody] = useState('');
  const [busy, setBusy] = useState(false);

  const isOwner = user && post.user && user.username === post.user.username;

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
      // Not critical enough to interrupt the feed.
    } finally {
      setBusy(false);
    }
  };

  const togglePin = async () => {
    if (busy) return;
    setBusy(true);
    try {
      await api(`/posts/${post.id}/pin`, { method: pinned ? 'DELETE' : 'POST' });
      setPinned(!pinned);
      onChange?.();
    } catch {
      // ignore
    } finally {
      setBusy(false);
    }
  };

  const fetchComments = async () => {
    const res = await api(`/posts/${post.id}/comments`);
    setComments(res.data);
  };

  useEffect(() => {
    if (showComments && comments === null) fetchComments();
  }, [showComments]);

  const loadComments = () => setShowComments((v) => !v);

  const submitComment = async (e) => {
    e.preventDefault();
    if (!commentBody.trim()) return;
    const res = await api(`/posts/${post.id}/comments`, { method: 'POST', body: { body: commentBody } });
    setComments((prev) => [...(prev || []), res.data]);
    setCommentBody('');
  };

  return (
    <div className={`post-card${pinned ? ' post-card--pinned' : ''}`}>
      <div className="post-card__head">
        <Avatar name={post.user?.username} />
        <div className="post-card__author-block">
          <Link to={`/u/${post.user?.username}`} className="post-card__author">
            {post.user?.username}
          </Link>
          <span className="post-card__time">
            <Link to={`/posts/${post.id}`} style={{ color: 'inherit' }}>
              {timeAgo(post.created_at)}
            </Link>
            {pinned && <span className="post-card__pin-flag"> · 📌 Pinned</span>}
          </span>
        </div>
        <span className={`post-card__type post-card__type--${post.type}`}>{TYPE_LABELS[post.type] || post.type}</span>
      </div>
      {post.plant && <span className="tag" style={{ marginBottom: 8, display: 'inline-block' }}>🌿 {post.plant.name}</span>}
      <p className="post-card__body">{post.body}</p>
      <div className="post-card__actions">
        <button className={`icon-btn${liked ? ' liked' : ''}`} onClick={toggleLike} disabled={!user}>
          {liked ? '♥' : '♡'} {likesCount}
        </button>
        <button className="icon-btn" onClick={loadComments}>
          💬 {post.comments_count ?? ''} Comments
        </button>
        {isOwner && (
          <button className={`icon-btn${pinned ? ' pinned' : ''}`} onClick={togglePin} disabled={busy}>
            📌 {pinned ? 'Unpin' : 'Pin to profile'}
          </button>
        )}
      </div>

      {showComments && (
        <div className="post-card__comments">
          {comments === null && <div className="loading">Loading comments...</div>}
          {comments?.map((c) => (
            <div className="comment" key={c.id}>
              <Avatar name={c.user?.username} size={26} />
              <div>
                <strong>{c.user?.username}</strong>
                {c.body}
              </div>
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
                style={{ flex: 1, padding: '9px 14px', borderRadius: 999, border: '1.5px solid var(--sand-dark)' }}
              />
              <button className="btn btn--sm" type="submit">
                Post
              </button>
            </form>
          )}
        </div>
      )}
    </div>
  );
}
