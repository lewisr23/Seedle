import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { useCart } from '../context/CartContext';
import ProductCard from '../components/ProductCard';

const CATEGORIES = [
  { value: '', label: 'All categories' },
  { value: 'seed', label: 'Seeds' },
  { value: 'live_plant', label: 'Live Plants' },
  { value: 'tool', label: 'Tools' },
  { value: 'fertilizer', label: 'Fertilizer' },
  { value: 'other', label: 'Other' },
];

export default function Home() {
  const { addItem } = useCart();
  const [q, setQ] = useState('');
  const [category, setCategory] = useState('');
  const [minPrice, setMinPrice] = useState('');
  const [maxPrice, setMaxPrice] = useState('');
  const [page, setPage] = useState(1);
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [added, setAdded] = useState(null);

  useEffect(() => {
    setLoading(true);
    const timeout = setTimeout(() => {
      api('/products', {
        params: {
          q: q || undefined,
          category: category || undefined,
          min_price: minPrice ? Math.round(minPrice * 100) : undefined,
          max_price: maxPrice ? Math.round(maxPrice * 100) : undefined,
          page,
        },
      })
        .then(setResult)
        .finally(() => setLoading(false));
    }, 300);

    return () => clearTimeout(timeout);
  }, [q, category, minPrice, maxPrice, page]);

  const handleAdd = (product) => {
    addItem(product, 1);
    setAdded(product.id);
    setTimeout(() => setAdded(null), 1200);
  };

  return (
    <div className="page container">
      <h1 className="page-title">Marketplace</h1>
      <p className="page-subtitle">
        Seeds, plants and tools from gardeners in the community.
        {result?.source === 'database' && (
          <span className="tag" style={{ marginLeft: 8 }}>
            search running on database fallback (Elasticsearch offline)
          </span>
        )}
      </p>

      <div className="filters">
        <input
          type="text"
          placeholder="Search products..."
          value={q}
          onChange={(e) => {
            setPage(1);
            setQ(e.target.value);
          }}
          style={{ minWidth: 220 }}
        />
        <select
          value={category}
          onChange={(e) => {
            setPage(1);
            setCategory(e.target.value);
          }}
        >
          {CATEGORIES.map((c) => (
            <option key={c.value} value={c.value}>
              {c.label}
            </option>
          ))}
        </select>
        <input
          type="number"
          placeholder="Min £"
          value={minPrice}
          onChange={(e) => {
            setPage(1);
            setMinPrice(e.target.value);
          }}
          style={{ width: 90 }}
        />
        <input
          type="number"
          placeholder="Max £"
          value={maxPrice}
          onChange={(e) => {
            setPage(1);
            setMaxPrice(e.target.value);
          }}
          style={{ width: 90 }}
        />
      </div>

      {loading && <div className="loading">Loading products...</div>}

      {!loading && result?.data?.length === 0 && (
        <div className="empty-state">No products match those filters.</div>
      )}

      {!loading && result?.data?.length > 0 && (
        <>
          <div className="grid">
            {result.data.map((p) => (
              <div key={p.id}>
                <ProductCard product={p} onAddToCart={handleAdd} />
                {added === p.id && (
                  <div className="alert alert--success" style={{ marginTop: 6, padding: '6px 10px' }}>
                    Added to cart
                  </div>
                )}
              </div>
            ))}
          </div>

          <div className="flex-between" style={{ marginTop: 24 }}>
            <button
              className="btn btn--outline"
              disabled={page <= 1}
              onClick={() => setPage((p) => p - 1)}
            >
              Previous
            </button>
            <span style={{ fontSize: 13, color: '#4f5a52' }}>
              Page {result.page} · {result.total} results
            </span>
            <button
              className="btn btn--outline"
              disabled={result.page * result.per_page >= result.total}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </button>
          </div>
        </>
      )}
    </div>
  );
}
