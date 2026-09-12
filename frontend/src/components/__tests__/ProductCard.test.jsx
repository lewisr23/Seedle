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
  price_pounds: 3.5,
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
  it('shows title, price, stock and seller, and links to the listing', () => {
    renderCard();

    expect(screen.getByText('Tomato seeds')).toBeInTheDocument();
    expect(screen.getByText('£3.50')).toBeInTheDocument();
    expect(screen.getByText(/6 in stock/)).toBeInTheDocument();
    expect(screen.getByText(/daphne/)).toBeInTheDocument();
    expect(screen.getAllByRole('link')[0]).toHaveAttribute('href', '/products/4');
  });

  it('says out of stock rather than "0 in stock"', () => {
    renderCard(product({ stock: 0 }));

    expect(screen.getByText(/out of stock/i)).toBeInTheDocument();
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

describe('adding to the cart', () => {
  it('hands the product back to the caller', async () => {
    const onAddToCart = vi.fn();
    renderCard(product(), onAddToCart);

    await userEvent.click(screen.getByRole('button', { name: 'Add' }));

    expect(onAddToCart).toHaveBeenCalledWith(expect.objectContaining({ id: 4 }));
  });

  it('disables Add when there is nothing to sell', () => {
    renderCard(product({ stock: 0 }));

    expect(screen.getByRole('button', { name: 'Add' })).toBeDisabled();
  });

  it('does not blow up when no handler is passed', async () => {
    renderCard();

    await userEvent.click(screen.getByRole('button', { name: 'Add' }));

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
