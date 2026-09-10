import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { ApiError } from '../api/client';

const ZONES = Array.from({ length: 11 }, (_, i) => i + 2); // zones 2-12

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    hardiness_zone: '',
    location: '',
  });
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const update = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    setErrors({});
    setBusy(true);
    try {
      await register(form);
      navigate('/', { replace: true });
    } catch (err) {
      if (err instanceof ApiError && err.errors) {
        setErrors(err.errors);
      } else {
        setError(err instanceof ApiError ? err.message : 'Something went wrong.');
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="page container">
      <h1 className="page-title">Create your account</h1>
      <p className="page-subtitle">Join a community of gardeners buying, selling and helping each other grow.</p>

      {error && <div className="alert alert--error">{error}</div>}

      <form className="form" onSubmit={submit}>
        <div className="field">
          <label>Name</label>
          <input value={form.name} onChange={update('name')} required />
          {errors.name && <span className="error-text">{errors.name[0]}</span>}
        </div>
        <div className="field">
          <label>Username</label>
          <input value={form.username} onChange={update('username')} required />
          {errors.username && <span className="error-text">{errors.username[0]}</span>}
        </div>
        <div className="field">
          <label>Email</label>
          <input type="email" value={form.email} onChange={update('email')} required />
          {errors.email && <span className="error-text">{errors.email[0]}</span>}
        </div>
        <div className="field">
          <label>Password</label>
          <input type="password" value={form.password} onChange={update('password')} required />
          {errors.password && <span className="error-text">{errors.password[0]}</span>}
        </div>
        <div className="field">
          <label>Confirm password</label>
          <input
            type="password"
            value={form.password_confirmation}
            onChange={update('password_confirmation')}
            required
          />
        </div>
        <div className="field">
          <label>Hardiness zone (helps us recommend what to plant)</label>
          <select value={form.hardiness_zone} onChange={update('hardiness_zone')}>
            <option value="">Not sure</option>
            {ZONES.map((z) => (
              <option key={z} value={z}>
                Zone {z}
              </option>
            ))}
          </select>
        </div>
        <div className="field">
          <label>Location (optional)</label>
          <input value={form.location} onChange={update('location')} placeholder="e.g. York, UK" />
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Creating account...' : 'Sign up'}
        </button>
      </form>

      <p style={{ marginTop: 16, fontSize: 14 }}>
        Already have an account? <Link to="/login">Log in</Link>
      </p>
    </div>
  );
}
