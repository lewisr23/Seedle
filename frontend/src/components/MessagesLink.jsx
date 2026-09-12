import { useEffect, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { api } from '../api/client';

/**
 * Navbar entry for the inbox, with an unread badge. Polls on the same cadence
 * as the notification bell, and re-checks on navigation so opening a thread
 * clears the badge without waiting out the interval.
 */
export default function MessagesLink() {
  const [unread, setUnread] = useState(0);
  const location = useLocation();

  useEffect(() => {
    const load = () =>
      api('/conversations/unread-count')
        .then((res) => setUnread(res.unread_count))
        .catch(() => {
          // A failed poll shouldn't break the navbar.
        });

    load();
    const interval = setInterval(load, 30000);
    return () => clearInterval(interval);
  }, [location.pathname]);

  return (
    <NavLink to="/messages" className="navbar__cart" aria-label="Messages">
      ✉️
      {unread > 0 && <span className="navbar__cart-badge">{unread}</span>}
    </NavLink>
  );
}
