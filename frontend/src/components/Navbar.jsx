import { NavLink } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useCart } from '../context/CartContext';

export default function Navbar() {
  const { user, logout } = useAuth();
  const { totalItems } = useCart();

  return (
    <nav className="navbar">
      <div className="navbar__inner">
        <NavLink to="/" className="navbar__brand">
          🌱 GrowGuide
        </NavLink>
        <div className="navbar__links">
          <NavLink to="/" className={({ isActive }) => `navbar__link${isActive ? ' active' : ''}`} end>
            Marketplace
          </NavLink>
          <NavLink to="/garden" className={({ isActive }) => `navbar__link${isActive ? ' active' : ''}`}>
            Garden Planner
          </NavLink>
          <NavLink to="/feed" className={({ isActive }) => `navbar__link${isActive ? ' active' : ''}`}>
            Feed
          </NavLink>
          {user && (
            <NavLink to="/sell" className={({ isActive }) => `navbar__link${isActive ? ' active' : ''}`}>
              Sell
            </NavLink>
          )}
        </div>
        <div className="navbar__right">
          <NavLink to="/cart" className="navbar__cart">
            🛒
            {totalItems > 0 && <span className="navbar__cart-badge">{totalItems}</span>}
          </NavLink>
          {user ? (
            <>
              <NavLink to={`/u/${user.username}`} className="navbar__link">
                {user.username}
              </NavLink>
              <button className="btn btn--ghost" onClick={logout}>
                Log out
              </button>
            </>
          ) : (
            <>
              <NavLink to="/login" className="navbar__link">
                Log in
              </NavLink>
              <NavLink to="/register" className="btn">
                Sign up
              </NavLink>
            </>
          )}
        </div>
      </div>
    </nav>
  );
}
