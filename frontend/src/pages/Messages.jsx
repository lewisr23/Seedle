import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import Avatar from '../components/Avatar';
import { timeAgo } from '../utils/time';

export default function Messages() {
  const [conversations, setConversations] = useState(null);

  useEffect(() => {
    api('/conversations').then((res) => setConversations(res.data));
  }, []);

  if (conversations === null) {
    return <div className="page container loading">Loading messages...</div>;
  }

  return (
    <div className="page container">
      <h1 className="page-title">Messages</h1>

      {conversations.length === 0 && (
        <div className="empty-state">
          <span className="empty-state__icon">✉️</span>
          No messages yet. Ask a seller about one of their listings to start a conversation.
        </div>
      )}

      {conversations.map((conversation) => (
        <Link
          key={conversation.id}
          to={`/messages/${conversation.id}`}
          className="card"
          style={{
            display: 'block',
            padding: 16,
            marginBottom: 12,
            textDecoration: 'none',
            color: 'inherit',
          }}
        >
          <div className="flex-between">
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, minWidth: 0 }}>
              <Avatar name={conversation.counterpart.username} size={34} />
              <div style={{ minWidth: 0 }}>
                <strong>{conversation.counterpart.username}</strong>
                {conversation.product && (
                  <div style={{ fontSize: 13, color: 'var(--ink-500)' }}>
                    about {conversation.product.title}
                  </div>
                )}
                {conversation.latest_message && (
                  <div
                    style={{
                      fontSize: 13.5,
                      color: 'var(--ink-500)',
                      marginTop: 2,
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                      whiteSpace: 'nowrap',
                      maxWidth: 460,
                    }}
                  >
                    {conversation.latest_message.is_mine && 'You: '}
                    {conversation.latest_message.body}
                  </div>
                )}
              </div>
            </div>
            <div style={{ textAlign: 'right', flexShrink: 0 }}>
              {conversation.unread_count > 0 && (
                <span className="badge badge--unread">{conversation.unread_count}</span>
              )}
              {conversation.last_message_at && (
                <div style={{ fontSize: 12, color: 'var(--ink-500)', marginTop: 4 }}>
                  {timeAgo(conversation.last_message_at)}
                </div>
              )}
            </div>
          </div>
        </Link>
      ))}
    </div>
  );
}
