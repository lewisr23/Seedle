import { Link } from 'react-router-dom';
import Stars from './Stars';

const CATEGORY_META = {
  seed: { label: 'Seeds', icon: '🌱' },
  live_plant: { label: 'Live Plant', icon: '🪴' },
  tool: { label: 'Tool', icon: '🛠️' },
  fertilizer: { label: 'Fertilizer', icon: '🌾' },
  other: { label: 'Other', icon: '🧺' },
};

export default function ProductCard({ product, onAddToCart }) {
  const meta = CATEGORY_META[product.category] || CATEGORY_META.other;

  return (
    <div className="product-card">
      <Link to={`/products/${product.id}`} className={`product-card__media media-${product.category}`}>
        {meta.icon}
      </Link>
      <div className="product-card__body">
        <span className="product-card__category">{meta.label}</span>
        <Link to={`/products/${product.id}`}>
          <p className="product-card__title">{product.title}</p>
        </Link>
        {product.reviews_count > 0 && (
          <span style={{ display: 'flex', alignItems: 'center', gap: 5 }}>
            <Stars value={product.rating_average} size={12} />
            <span style={{ fontSize: 12, color: 'var(--ink-300)' }}>({product.reviews_count})</span>
          </span>
        )}
        <span className="product-card__meta">
          {product.stock > 0 ? `${product.stock} in stock` : 'Out of stock'} · sold by{' '}
          {product.seller?.username}
        </span>
        <div className="product-card__footer">
          <span className="product-card__price">£{product.price_pounds?.toFixed(2)}</span>
          <button
            className="btn btn--sm"
            disabled={product.stock <= 0}
            onClick={() => onAddToCart?.(product)}
          >
            Add
          </button>
        </div>
      </div>
    </div>
  );
}
