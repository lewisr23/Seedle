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

const SUN_OPTIONS = [
  { value: '', label: 'Any sun' },
  { value: 'full_sun', label: 'Full sun' },
  { value: 'partial_sun', label: 'Partial sun' },
  { value: 'shade', label: 'Shade' },
];

const SORT_OPTIONS = [
  { value: '', label: 'Newest first' },
  { value: 'rating_desc', label: 'Top rated' },
  { value: 'distance', label: 'Nearest first' },
];

const CATEGORY_LABELS = Object.fromEntries(CATEGORIES.filter((c) => c.value).map((c) => [c.value, c.label]));

const DEFAULT_FILTERS = {
  q: '',
  category: '',
  sunRequirement: '',
  zone: '',
  inStock: false,
  sort: '',
  radiusKm: '',
};

export default function Home() {
  const { addItem } = useCart();
  const [heroQuery, setHeroQuery] = useState('');
  const [filters, setFilters] = useState(DEFAULT_FILTERS);
  const [showMoreFilters, setShowMoreFilters] = useState(false);
  const [page, setPage] = useState(1);
  const [result, setResult] = useState(null);
  const [loading, setLoading] = useState(true);
  const [added, setAdded] = useState(null);
  const [stats, setStats] = useState(null);

  const setFilter = (key) => (value) => {
    setPage(1);
    setFilters((f) => ({ ...f, [key]: value }));
  };

  useEffect(() => {
    Promise.all([
      api('/products', { params: { per_page: 1 } }),
      api('/guides', { params: { per_page: 1 } }),
      api('/posts', { params: { per_page: 1 } }),
    ]).then(([products, guides, posts]) => {
      setStats({
        products: products.total,
        guides: guides.meta?.total ?? 0,
        posts: posts.meta?.total ?? 0,
      });
    });
  }, []);

  useEffect(() => {
    setLoading(true);
    const timeout = setTimeout(() => {
      api('/products', {
        params: {
          q: filters.q || undefined,
          category: filters.category || undefined,
          sun_requirement: filters.sunRequirement || undefined,
          zone: filters.zone || undefined,
          in_stock: filters.inStock ? 1 : undefined,
          sort: filters.sort || undefined,
          radius_km: filters.radiusKm || undefined,
          page,
        },
      })
        .then(setResult)
        .finally(() => setLoading(false));
    }, 300);

    return () => clearTimeout(timeout);
  }, [filters, page]);

  const handleAdd = (product) => {
    addItem(product, 1);
    setAdded(product.id);
    setTimeout(() => setAdded(null), 1200);
  };

  const submitHeroSearch = (e) => {
    e.preventDefault();
    setFilter('q')(heroQuery);
    document.getElementById('marketplace-results')?.scrollIntoView({ behavior: 'smooth' });
  };

  const activeExtraFilters = [
    filters.sunRequirement && SUN_OPTIONS.find((o) => o.value === filters.sunRequirement)?.label,
    filters.zone && `Zone ${filters.zone}`,
    filters.inStock && 'In stock only',
  ].filter(Boolean);

  const clearFilters = () => {
    setFilters(DEFAULT_FILTERS);
    setHeroQuery('');
    setPage(1);
  };

  const hasAnyFilter = Object.entries(filters).some(([key, value]) =>
    key === 'inStock' ? value : Boolean(value)
  );

  return (
    <div className="page container">
      <div className="hero">
        <div className="hero__inner">
          <span className="eyebrow" style={{ color: 'var(--terracotta-400)' }}>
            A community for gardeners
          </span>
          <h1>Grow something worth talking about</h1>
          <p>
            Pass on spare seeds, cuttings and tools to other gardeners, get warned before you plant bad
            neighbours, and learn from guides written for people who'd rather be outside than reading manuals.
          </p>
          <form className="hero__search" onSubmit={submitHeroSearch}>
            <input
              type="text"
              placeholder="Search seeds, plants, tools..."
              value={heroQuery}
              onChange={(e) => setHeroQuery(e.target.value)}
            />
            <button className="btn btn--accent" type="submit">
              Search
            </button>
          </form>
          {stats && (
            <div className="hero__stats">
              <div>
                <strong>{stats.products.toLocaleString()}</strong>
                <span>Listings</span>
              </div>
              <div>
                <strong>{stats.guides.toLocaleString()}</strong>
                <span>Guides</span>
              </div>
              <div>
                <strong>{stats.posts.toLocaleString()}</strong>
                <span>Community posts</span>
              </div>
            </div>
          )}
        </div>
      </div>

      <div id="marketplace-results">
        <div className="flex-between" style={{ marginBottom: 4 }}>
          <h2 className="section-title" style={{ marginBottom: 0 }}>
            The swap shelf
          </h2>
          {result?.source === 'database' && (
            <span className="tag">search running on database fallback</span>
          )}
        </div>
        <p className="page-subtitle">Seeds, plants and tools from gardeners in the community.</p>

        <div className="filters">
          <input
            type="text"
            placeholder="Search products..."
            value={filters.q}
            onChange={(e) => {
              setFilter('q')(e.target.value);
              setHeroQuery(e.target.value);
            }}
            style={{ minWidth: 220 }}
          />
          <select value={filters.category} onChange={(e) => setFilter('category')(e.target.value)}>
            {CATEGORIES.map((c) => (
              <option key={c.value} value={c.value}>
                {c.label}
              </option>
            ))}
          </select>
          <select
            value={filters.radiusKm}
            onChange={(e) => setFilter('radiusKm')(e.target.value)}
            aria-label="Distance"
          >
            <option value="">Anywhere</option>
            <option value="5">Within 5 km</option>
            <option value="15">Within 15 km</option>
            <option value="30">Within 30 km</option>
            <option value="75">Within 75 km</option>
          </select>
          <select value={filters.sort} onChange={(e) => setFilter('sort')(e.target.value)}>
            {SORT_OPTIONS.map((s) => (
              <option key={s.value} value={s.value}>
                {s.label}
              </option>
            ))}
          </select>
          <button
            type="button"
            className={`category-pill${showMoreFilters ? ' active' : ''}`}
            onClick={() => setShowMoreFilters((v) => !v)}
          >
            More filters {activeExtraFilters.length > 0 && `(${activeExtraFilters.length})`}
          </button>
          {hasAnyFilter && (
            <button type="button" className="btn btn--ghost btn--sm" onClick={clearFilters}>
              Clear all
            </button>
          )}
        </div>

        {showMoreFilters && (
          <div className="filters" style={{ marginTop: -10 }}>
            <select value={filters.sunRequirement} onChange={(e) => setFilter('sunRequirement')(e.target.value)}>
              {SUN_OPTIONS.map((s) => (
                <option key={s.value} value={s.value}>
                  {s.label}
                </option>
              ))}
            </select>
            <input
              type="number"
              placeholder="Your zone"
              value={filters.zone}
              onChange={(e) => setFilter('zone')(e.target.value)}
              style={{ width: 110 }}
            />
            <label
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 7,
                fontSize: 14,
                padding: '10px 14px',
                border: '1.5px solid var(--sand-dark)',
                borderRadius: 999,
                background: 'var(--white)',
              }}
            >
              <input
                type="checkbox"
                checked={filters.inStock}
                onChange={(e) => setFilter('inStock')(e.target.checked)}
                style={{ width: 'auto' }}
              />
              In stock only
            </label>
          </div>
        )}

        {result?.facets?.category && Object.keys(result.facets.category).length > 0 && (
          <div className="facet-pills">
            {Object.entries(result.facets.category).map(([key, count]) => (
              <span key={key}>
                {CATEGORY_LABELS[key] || key}: {count}
              </span>
            ))}
          </div>
        )}

        {loading && <div className="loading">Loading products...</div>}

        {!loading && result?.data?.length === 0 && (
          <div className="empty-state">
            <span className="empty-state__icon">🌾</span>
            No products match those filters.
            {hasAnyFilter && (
              <div style={{ marginTop: 10 }}>
                <button className="btn btn--outline btn--sm" onClick={clearFilters}>
                  Clear filters
                </button>
              </div>
            )}
          </div>
        )}

        {!loading && result?.data?.length > 0 && (
          <>
            <div className="grid">
              {result.data.map((p) => (
                <div key={p.id}>
                  <ProductCard product={p} onAddToCart={handleAdd} />
                  {added === p.id && (
                    <div className="alert alert--success" style={{ marginTop: 6, padding: '6px 10px' }}>
                      Added to your list
                    </div>
                  )}
                </div>
              ))}
            </div>

            <div className="flex-between" style={{ marginTop: 28 }}>
              <button className="btn btn--outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                Previous
              </button>
              <span style={{ fontSize: 13, color: 'var(--ink-500)' }}>
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
    </div>
  );
}
