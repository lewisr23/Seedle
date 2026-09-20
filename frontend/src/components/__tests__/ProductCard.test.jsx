import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ProductCard from '../ProductCard';

// The card embeds a SaveButton, which needs both contexts. Stubbing them keeps
// this test about the card itself.
vi.mock('../../context/AuthContext', () => ({
  useAuth: () => ({ user: { id: 1 } }),
}));
const toggle = vi.fn();
vi.mock('../../context/SavedContext', () => ({
  useSaved: () => ({ isSaved: () => false, toggle }),
}));

const product = (extra = {}) => ({
  id: 4,
  title: 'Tomato seeds',
  category: 'seed',
  stock: 6,
  seller: { id: 2, username: 'daphne' },
  reviews_count: 0,
  rating_average: null,
  ...extra,
});

function renderCard(p = product(), onAddToCart) {
  return render(
    <MemoryRouter>
      <ProductCard product={p} onAddToCart={onAddToCart} />
    </MemoryRouter>
  );
}

beforeEach(() => toggle.mockReset());

describe('what the card shows', () => {
  it('shows title, availability and who is offering it, and links to the listing', () => {
    renderCard();

    expect(screen.getByText('Tomato seeds')).toBeInTheDocument();
    expect(screen.getByText(/6 available/)).toBeInTheDocument();
    expect(screen.getByText(/daphne/)).toBeInTheDocument();
    expect(screen.getAllByRole('link')[0]).toHaveAttribute('href', '/products/4');
  });

  it('says all gone rather than "0 available"', () => {
    renderCard(product({ stock: 0 }));

    expect(screen.getByText(/all gone/i)).toBeInTheDocument();
  });

  it('hides the rating until there is at least one review', () => {
    renderCard();

    expect(screen.queryByLabelText(/out of 5/)).not.toBeInTheDocument();
  });

  it('shows the rating and review count once reviewed', () => {
    renderCard(product({ reviews_count: 3, rating_average: 4.0 }));

    expect(screen.getByLabelText('4 out of 5')).toBeInTheDocument();
    expect(screen.getByText('(3)')).toBeInTheDocument();
  });
});

describe('the photo', () => {
  it('shows the first uploaded photo when there is one', () => {
    renderCard(product({ images: ['http://localhost:8000/api/images/product-images/a.jpg'] }));

    const img = screen.getByRole('img', { name: 'Tomato seeds' });
    expect(img).toHaveAttribute('src', 'http://localhost:8000/api/images/product-images/a.jpg');
    expect(img).toHaveAttribute('loading', 'lazy');
  });

  it('falls back to the category emoji with no photos', () => {
    renderCard(product({ images: [] }));

    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(screen.getByText('🌱')).toBeInTheDocument();
  });

  it('copes with images being absent entirely', () => {
    const p = product();
    delete p.images;

    renderCard(p);

    expect(screen.getByText('🌱')).toBeInTheDocument();
  });
});

describe('distance', () => {
  it('shows how far away it is on a radius search', () => {
    renderCard(product({ distance_km: 12.4 }));

    expect(screen.getByText(/12.4 km away/)).toBeInTheDocument();
  });

  it('shows nothing when the search was not a radius search', () => {
    renderCard();

    expect(screen.queryByText(/km away/)).not.toBeInTheDocument();
  });
});

describe('adding to the cart', () => {
  it('hands the product back to the caller', async () => {
    const onAddToCart = vi.fn();
    renderCard(product(), onAddToCart);

    await userEvent.click(screen.getByRole('button', { name: 'Request' }));

    expect(onAddToCart).toHaveBeenCalledWith(expect.objectContaining({ id: 4 }));
  });

  it('disables the request button when there is nothing left', () => {
    renderCard(product({ stock: 0 }));

    expect(screen.getByRole('button', { name: 'Request' })).toBeDisabled();
  });

  it('does not blow up when no handler is passed', async () => {
    renderCard();

    await userEvent.click(screen.getByRole('button', { name: 'Request' }));

    expect(screen.getByText('Tomato seeds')).toBeInTheDocument();
  });
});

describe('saving from the card', () => {
  it('offers a heart that toggles this product', async () => {
    renderCard();

    await userEvent.click(screen.getByRole('button', { name: /save for later/i }));

    expect(toggle).toHaveBeenCalledWith('product', 4);
  });
});
