import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useSaved } from '../context/SavedContext';

/**
 * Heart toggle for a listing or a plant. Signed-out visitors get sent to log
 * in rather than a dead control, since saving needs an account.
 */
export default function SaveButton({ type, id, returnTo, size = 20 }) {
  const { user } = useAuth();
  const { isSaved, toggle } = useSaved();
  const navigate = useNavigate();
  const location = useLocation();
  // Send them back where they were unless the caller says otherwise.
  const back = returnTo ?? location.pathname;

  const saved = user ? isSaved(type, id) : false;

  const onClick = () => {
    if (!user) {
      navigate('/login', { state: { from: { pathname: back } } });
      return;
    }
    toggle(type, id);
  };

  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={saved}
      aria-label={saved ? 'Remove from saved' : 'Save for later'}
      title={saved ? 'Remove from saved' : 'Save for later'}
      style={{
        background: 'none',
        border: 'none',
        cursor: 'pointer',
        fontSize: size,
        lineHeight: 1,
        padding: 4,
      }}
    >
      {saved ? '♥' : '♡'}
    </button>
  );
}
