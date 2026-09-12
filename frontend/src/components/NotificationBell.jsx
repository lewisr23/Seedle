import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api/client';
import { timeAgo } from '../utils/time';

const ICONS = {
  new_sale: '💰',
  new_follower: '👋',
  new_comment: '💬',
  new_message: '✉️',
  order_status: '📦',
};

export default function NotificationBell() {
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState([]);
  const [unread, setUnread] = useState(0);
  const panelRef = useRef(null);
  const navigate = useNavigate();

  const load = () =>
    api('/notifications')
      .then((res) => {
        setItems(res.data);
        setUnread(res.unread_count);
      })
      .catch(() => {
        // A failed poll shouldn't break the navbar.
      });

  useEffect(() => {
    load();
    const interval = setInterval(load, 30000);
    return () => clearInterval(interval);
  }, []);

  // Close when clicking anywhere outside the panel.
  useEffect(() => {
    if (!open) return;

    const onClick = (e) => {
      if (panelRef.current && !panelRef.current.contains(e.target)) setOpen(false);
    };

    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [open]);

  const toggle = () => {
    setOpen((v) => !v);
    if (!open) load();
  };

  const markAllRead = async () => {
    await api('/notifications/read-all', { method: 'POST' });
    setUnread(0);
    setItems((prev) => prev.map((n) => ({ ...n, read: true })));
  };

  const openNotification = async (notification) => {
    setOpen(false);
    if (!notification.read) {
      api(`/notifications/${notification.id}/read`, { method: 'POST' }).then(load);
    }
    if (notification.link) navigate(notification.link);
  };

  return (
    <div className="notif" ref={panelRef}>
      <button className="notif__button" onClick={toggle} aria-label="Notifications">
        🔔
        {unread > 0 && <span className="notif__badge">{unread > 9 ? '9+' : unread}</span>}
      </button>

      {open && (
        <div className="notif__panel">
          <div className="notif__header">
            <strong>Notifications</strong>
            {unread > 0 && (
              <button className="notif__mark-read" onClick={markAllRead}>
                Mark all read
              </button>
            )}
          </div>

          {items.length === 0 && <div className="notif__empty">Nothing yet — go plant something.</div>}

          {items.map((n) => (
            <button
              key={n.id}
              className={`notif__item${n.read ? '' : ' notif__item--unread'}`}
              onClick={() => openNotification(n)}
            >
              <span className="notif__icon">{ICONS[n.type] || '🌱'}</span>
              <span className="notif__text">
                <strong>{n.title}</strong>
                <span>{n.body}</span>
                <em>{timeAgo(n.created_at)}</em>
              </span>
              {n.amount_pence != null && (
                <span className="notif__amount">£{(n.amount_pence / 100).toFixed(2)}</span>
              )}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
