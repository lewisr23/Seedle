import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import Avatar from './Avatar';

export default function SuggestedGardeners() {
  const [users, setUsers] = useState(null);
  const [followed, setFollowed] = useState({});

  useEffect(() => {
    api('/suggested-gardeners')
      .then((res) => setUsers(res.data))
      .catch(() => setUsers([]));
  }, []);

  const follow = async (username) => {
    setFollowed((f) => ({ ...f, [username]: 'busy' }));
    try {
      await api(`/users/${username}/follow`, { method: 'POST' });
      setFollowed((f) => ({ ...f, [username]: 'done' }));
    } catch {
      setFollowed((f) => ({ ...f, [username]: undefined }));
    }
  };

  if (users === null || users.length === 0) return null;

  return (
    <div className="card suggest">
      <h3 className="suggest__title">Gardeners to follow</h3>
      {users.map((u) => (
        <div className="suggest__row" key={u.id}>
          <Avatar name={u.username} size={34} />
          <div className="suggest__info">
            <Link to={`/u/${u.username}`} className="suggest__name">
              {u.username}
            </Link>
            <span>
              {u.followers_count ?? 0} followers
              {u.products_count > 0 && ` · ${u.products_count} listings`}
            </span>
          </div>
          <button
            className={`btn btn--sm ${followed[u.username] === 'done' ? 'btn--outline' : ''}`}
            disabled={followed[u.username] === 'busy' || followed[u.username] === 'done'}
            onClick={() => follow(u.username)}
          >
            {followed[u.username] === 'done' ? 'Following' : 'Follow'}
          </button>
        </div>
      ))}
    </div>
  );
}
