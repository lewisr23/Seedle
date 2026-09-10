import { Link } from 'react-router-dom';

const CATEGORY_LABELS = {
  seed: 'Seeds',
  live_plant: 'Live Plant',
  tool: 'Tool',
  fertilizer: 'Fertilizer',
  other: 'Other',
};

export default function ProductCard({ product, onAddToCart }) {
  return (
    <div className="product-card">
      <span className="product-card__category">{CATEGORY_LABELS[product.category] || product.category}</span>
      <Link to={`/products/${product.id}`}>
        <p className="product-card__title">{product.title}</p>
      </Link>
      <span className="product-card__meta">
        {product.stock > 0 ? `${product.stock} in stock` : 'Out of stock'} · sold by{' '}
        {product.seller?.username}
      </span>
      <div className="product-card__footer">
        <span className="product-card__price">£{product.price_pounds?.toFixed(2)}</span>
        <button
          className="btn"
          disabled={product.stock <= 0}
          onClick={() => onAddToCart?.(product)}
        >
          Add to cart
        </button>
      </div>
    </div>
  );
}
