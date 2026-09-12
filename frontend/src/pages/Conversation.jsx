import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, ApiError } from '../api/client';
import Avatar from '../components/Avatar';
import { timeAgo } from '../utils/time';

export default function Conversation() {
  const { id } = useParams();
  const [conversation, setConversation] = useState(null);
  const [body, setBody] = useState('');
  const [error, setError] = useState('');
  const [sending, setSending] = useState(false);
  const endRef = useRef(null);

  useEffect(() => {
    let cancelled = false;

    const load = (isPoll) =>
      api(`/conversations/${id}`)
        .then((res) => {
          if (cancelled) return;
          setConversation((prev) => {
            // A poll that brings nothing new keeps the existing object, so the
            // thread doesn't re-render (and re-scroll) every few seconds.
            if (isPoll && prev && prev.messages.length === res.data.messages.length) {
              return prev;
            }
            return res.data;
          });
        })
        .catch((err) => {
          if (cancelled) return;
          // Only a first load should surface an error; a dropped poll is not
          // worth throwing the whole thread away over.
          if (isPoll) return;
          setError(
            err instanceof ApiError && err.status === 403
              ? 'This conversation is not yours.'
              : 'Could not load this conversation.'
          );
        });

    load(false);
    // The other side's replies arrive out of band, so poll for them the same
    // way the orders page picks up async status changes.
    const interval = setInterval(() => load(true), 5000);

    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, [id]);

  // Keep the newest message in view, the way any thread view should behave.
  useEffect(() => {
    endRef.current?.scrollIntoView({ block: 'nearest' });
  }, [conversation?.messages?.length]);

  const send = async (event) => {
    event.preventDefault();
    const trimmed = body.trim();
    if (!trimmed) return;

    setError('');
    setSending(true);
    try {
      const res = await api(`/conversations/${id}/messages`, {
        method: 'POST',
        body: { body: trimmed },
      });
      setConversation((prev) => ({ ...prev, messages: [...prev.messages, res.data] }));
      setBody('');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not send that message.');
    } finally {
      setSending(false);
    }
  };

  if (error && !conversation) return <div className="page container"><div className="alert alert--error">{error}</div></div>;
  if (!conversation) return <div className="page container loading">Loading conversation...</div>;

  return (
    <div className="page container">
      <Link to="/messages" style={{ fontSize: 13.5 }}>← All messages</Link>

      <div className="flex-between" style={{ margin: '10px 0 16px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <Avatar name={conversation.counterpart.username} size={38} />
          <div>
            <h1 className="page-title" style={{ margin: 0, fontSize: 22 }}>
              {conversation.counterpart.username}
            </h1>
            {conversation.product && (
              <Link to={`/products/${conversation.product.id}`} style={{ fontSize: 13 }}>
                about {conversation.product.title}
              </Link>
            )}
          </div>
        </div>
      </div>

      <div className="card" style={{ padding: 16 }}>
        {conversation.messages.map((message) => (
          <div
            key={message.id}
            style={{
              display: 'flex',
              justifyContent: message.is_mine ? 'flex-end' : 'flex-start',
              marginBottom: 10,
            }}
          >
            <div
              style={{
                maxWidth: '72%',
                padding: '9px 13px',
                borderRadius: 12,
                background: message.is_mine ? 'var(--green-700)' : 'var(--sand-light, #f3f0e9)',
                color: message.is_mine ? '#fff' : 'inherit',
              }}
            >
              <div style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{message.body}</div>
              <div
                style={{
                  fontSize: 11,
                  marginTop: 4,
                  opacity: 0.7,
                }}
              >
                {timeAgo(message.created_at)}
              </div>
            </div>
          </div>
        ))}
        <div ref={endRef} />
      </div>

      {error && <div className="alert alert--error" style={{ marginTop: 12 }}>{error}</div>}

      <form onSubmit={send} style={{ display: 'flex', gap: 10, marginTop: 14 }}>
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          placeholder="Write a reply..."
          rows={2}
          maxLength={2000}
          style={{
            flex: 1,
            padding: '10px 12px',
            borderRadius: 10,
            border: '1.5px solid var(--sand-dark)',
            fontFamily: 'inherit',
            resize: 'vertical',
          }}
        />
        <button className="btn" type="submit" disabled={sending || !body.trim()}>
          {sending ? 'Sending...' : 'Send'}
        </button>
      </form>
    </div>
  );
}
