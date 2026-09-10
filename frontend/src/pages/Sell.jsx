import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, ApiError } from '../api/client';

const CATEGORIES = [
  { value: 'seed', label: 'Seeds' },
  { value: 'live_plant', label: 'Live Plant' },
  { value: 'tool', label: 'Tool' },
  { value: 'fertilizer', label: 'Fertilizer' },
  { value: 'other', label: 'Other' },
];

export default function Sell() {
  const navigate = useNavigate();
  const [plants, setPlants] = useState([]);
  const [form, setForm] = useState({
    title: '',
    description: '',
    category: 'seed',
    plant_id: '',
    price: '',
    stock: '1',
  });
  const [errors, setErrors] = useState({});
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api('/plants', { params: { per_page: 100 } }).then((res) => setPlants(res.data));
  }, []);

  const update = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));

  const submit = async (e) => {
    e.preventDefault();
    setError('');
    setErrors({});
    setBusy(true);
    try {
      const res = await api('/products', {
        method: 'POST',
        body: {
          title: form.title,
          description: form.description,
          category: form.category,
          plant_id: form.plant_id || null,
          price_pence: Math.round(Number(form.price) * 100),
          stock: Number(form.stock),
        },
      });
      navigate(`/products/${res.data.id}`);
    } catch (err) {
      if (err instanceof ApiError && err.errors) {
        setErrors(err.errors);
      } else {
        setError(err instanceof ApiError ? err.message : 'Could not create listing.');
      }
    } finally {
      setBusy(false);
    }
  };

  const usesPlant = form.category === 'seed' || form.category === 'live_plant';

  return (
    <div className="page container">
      <h1 className="page-title">List something for sale</h1>
      <p className="page-subtitle">Seeds, plants, tools or fertilizer — set your own price.</p>

      {error && <div className="alert alert--error">{error}</div>}

      <form className="form" onSubmit={submit}>
        <div className="field">
          <label>Title</label>
          <input value={form.title} onChange={update('title')} required />
          {errors.title && <span className="error-text">{errors.title[0]}</span>}
        </div>
        <div className="field">
          <label>Description</label>
          <textarea value={form.description} onChange={update('description')} />
        </div>
        <div className="field">
          <label>Category</label>
          <select value={form.category} onChange={update('category')}>
            {CATEGORIES.map((c) => (
              <option key={c.value} value={c.value}>
                {c.label}
              </option>
            ))}
          </select>
        </div>
        {usesPlant && (
          <div className="field">
            <label>Plant (optional — shows care info to buyers)</label>
            <select value={form.plant_id} onChange={update('plant_id')}>
              <option value="">None</option>
              {plants.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </div>
        )}
        <div className="field">
          <label>Price (£)</label>
          <input type="number" min="0.01" step="0.01" value={form.price} onChange={update('price')} required />
          {errors.price_pence && <span className="error-text">{errors.price_pence[0]}</span>}
        </div>
        <div className="field">
          <label>Stock</label>
          <input type="number" min="0" value={form.stock} onChange={update('stock')} required />
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Publishing...' : 'Publish listing'}
        </button>
      </form>
    </div>
  );
}
