import { useEffect, useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';
import Avatar from './Avatar';
import MessagesLink from './MessagesLink';
import NotificationBell from './NotificationBell';

const LINKS = [
  { to: '/', label: 'Swap shelf', end: true },
  { to: '/plants', label: 'Plants' },
  { to: '/guides', label: 'Guides' },
  { to: '/garden', label: 'Garden Planner' },
  { to: '/feed', label: 'Community' },
];

export default function Navbar() {
  const { user, logout } = useAuth();
  const { totalItems } = useCart();
  const [menuOpen, setMenuOpen] = useState(false);
  const location = useLocation();

  // Close the mobile menu automatically whenever the route changes.
  useEffect(() => setMenuOpen(false), [location.pathname]);

  const closeMenu = () => setMenuOpen(false);

  return (
    <nav className="navbar">
      <div className="navbar__inner">
        <NavLink to="/" className="navbar__brand" onClick={closeMenu}>
          <span className="mark">🌱</span>
          GrowGuide
        </NavLink>
        <div className="navbar__links">
          {LINKS.map((link) => (
            <NavLink
              key={link.to}
              to={link.to}
              end={link.end}
              className={({ isActive }) => `navbar__link${isActive ? ' active' : ''}`}
            >
              {link.label}
            </NavLink>
          ))}
          {user && (
            <NavLink to="/dashboard" className={({ isActive }) => `navbar__link${isActive ? ' active' : ''}`}>
              Offer
            </NavLink>
          )}
        </div>
        <div className="navbar__right">
          <NavLink to="/cart" className="navbar__cart">
            🛒
            {totalItems > 0 && <span className="navbar__cart-badge">{totalItems}</span>}
          </NavLink>
          {user && (
            <NavLink to="/saved" className="navbar__cart" aria-label="Saved items">
              ♡
            </NavLink>
          )}
          {user && <MessagesLink />}
          {user && <NotificationBell />}
          {user ? (
            <>
              <NavLink
                to={`/u/${user.username}`}
                className="navbar__link navbar__profile-link"
                style={{ display: 'flex', alignItems: 'center', gap: 8 }}
              >
                <Avatar name={user.username} size={26} />
                <span className="navbar__username">{user.username}</span>
              </NavLink>
              <button className="btn btn--ghost btn--sm navbar__logout" onClick={logout}>
                Log out
              </button>
            </>
          ) : (
            <>
              <NavLink to="/login" className="navbar__link navbar__login-link">
                Log in
              </NavLink>
              <NavLink to="/register" className="btn btn--sm">
                Sign up
              </NavLink>
            </>
          )}
          <button
            className="navbar__burger"
            aria-label="Toggle menu"
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen((v) => !v)}
          >
            {menuOpen ? '✕' : '☰'}
          </button>
        </div>
      </div>

      {menuOpen && (
        <div className="navbar__mobile-menu">
          {LINKS.map((link) => (
            <NavLink
              key={link.to}
              to={link.to}
              end={link.end}
              className={({ isActive }) => `navbar__mobile-link${isActive ? ' active' : ''}`}
              onClick={closeMenu}
            >
              {link.label}
            </NavLink>
          ))}
          {user && (
            <NavLink to="/dashboard" className="navbar__mobile-link" onClick={closeMenu}>
              Seller dashboard
            </NavLink>
          )}
          <div className="navbar__mobile-divider" />
          {user ? (
            <>
              <NavLink to={`/u/${user.username}`} className="navbar__mobile-link" onClick={closeMenu}>
                <Avatar name={user.username} size={22} /> {user.username}
              </NavLink>
              <NavLink to="/saved" className="navbar__mobile-link" onClick={closeMenu}>
                Saved
              </NavLink>
              <NavLink to="/messages" className="navbar__mobile-link" onClick={closeMenu}>
                Messages
              </NavLink>
              <NavLink to="/orders" className="navbar__mobile-link" onClick={closeMenu}>
                Your orders
              </NavLink>
              <button
                className="navbar__mobile-link navbar__mobile-logout"
                onClick={() => {
                  closeMenu();
                  logout();
                }}
              >
                Log out
              </button>
            </>
          ) : (
            <>
              <NavLink to="/login" className="navbar__mobile-link" onClick={closeMenu}>
                Log in
              </NavLink>
              <NavLink to="/register" className="navbar__mobile-link" onClick={closeMenu}>
                Sign up
              </NavLink>
            </>
          )}
        </div>
      )}
    </nav>
  );
}
