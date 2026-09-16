import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Cart from '../Cart';
import { CartProvider } from '../../context/CartContext';
import { ApiError, api } from '../../api/client';

vi.mock('../../api/client', async () => {
  const actual = await vi.importActual('../../api/client');
  return { ...actual, api: vi.fn() };
});

// Cart only reads `user` off the auth context; stubbing it keeps this test
// about checkout rather than session bootstrap.
const mockUser = vi.fn();
vi.mock('../../context/AuthContext', () => ({
  useAuth: () => ({ user: mockUser() }),
}));

const navigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return { ...actual, useNavigate: () => navigate };
});

const product = (id, extra = {}) => ({
  id,
  title: `Product ${id}`,
  stock: 10,
  ...extra,
});

function seedCart(lines) {
  localStorage.setItem('seedle_cart', JSON.stringify(lines));
}

function renderCart() {
  return render(
    <MemoryRouter>
      <CartProvider>
        <Cart />
      </CartProvider>
    </MemoryRouter>
  );
}

beforeEach(() => {
  vi.mocked(api).mockReset();
  navigate.mockReset();
  mockUser.mockReturnValue({ id: 1, username: 'alice' });
});

describe('empty state', () => {
  it('invites the user to browse when the list is empty', () => {
    renderCart();

    expect(screen.getByText(/nothing on your list yet/i)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /request these/i })).not.toBeInTheDocument();
  });
});

describe('line items', () => {
  it('lists every product on the list', () => {
    seedCart([
      { product: product(1), quantity: 2 },
      { product: product(2), quantity: 1 },
    ]);

    renderCart();

    expect(screen.getByText('Product 1')).toBeInTheDocument();
    expect(screen.getByText('Product 2')).toBeInTheDocument();
  });

  it('keeps the typed quantity', async () => {
    seedCart([{ product: product(1), quantity: 1 }]);
    renderCart();

    const qty = screen.getByRole('spinbutton');
    await userEvent.clear(qty);
    await userEvent.type(qty, '3');

    await waitFor(() => expect(screen.getByRole('spinbutton')).toHaveValue(3));
  });

  it('keeps the line when the quantity field is cleared mid-edit', async () => {
    seedCart([{ product: product(1), quantity: 2 }]);
    renderCart();

    // Select-all-then-delete is how people retype a number. Number('') is 0,
    // and updateQuantity treats 0 as "remove", so committing the empty string
    // straight to the cart used to delete the line out from under the user.
    await userEvent.clear(screen.getByRole('spinbutton'));

    expect(screen.getByText('Product 1')).toBeInTheDocument();
    expect(screen.queryByText(/your cart is empty/i)).not.toBeInTheDocument();
    expect(screen.getByRole('spinbutton')).toHaveValue(null); // shown empty while editing
  });

  it('restores the committed quantity if the field is left empty on blur', async () => {
    seedCart([{ product: product(1), quantity: 2 }]);
    renderCart();

    const qty = screen.getByRole('spinbutton');
    await userEvent.clear(qty);
    await userEvent.tab();

    expect(qty).toHaveValue(2);
  });

  it('drops a line when Remove is clicked', async () => {
    seedCart([
      { product: product(1), quantity: 1 },
      { product: product(2), quantity: 1 },
    ]);
    renderCart();

    await userEvent.click(screen.getAllByRole('button', { name: /remove/i })[0]);

    expect(screen.queryByText('Product 1')).not.toBeInTheDocument();
    expect(screen.getByText('Product 2')).toBeInTheDocument();
  });
});

describe('checkout', () => {
  it('posts every line and confirms the swap, then empties the list', async () => {
    seedCart([
      { product: product(1), quantity: 2 },
      { product: product(2), quantity: 1 },
    ]);
    vi.mocked(api).mockResolvedValue({ data: { id: 42 } });

    renderCart();
    await userEvent.click(screen.getByRole('button', { name: /request these/i }));

    await waitFor(() => expect(screen.getByText(/Swap #42 requested/)).toBeInTheDocument());

    expect(api).toHaveBeenCalledWith('/checkout', {
      method: 'POST',
      body: {
        items: [
          { product_id: 1, quantity: 2 },
          { product_id: 2, quantity: 1 },
        ],
      },
    });
    expect(JSON.parse(localStorage.getItem('seedle_cart'))).toEqual([]);
  });

  it('shows the server message when stock ran out, and keeps the list intact', async () => {
    seedCart([{ product: product(1), quantity: 2 }]);
    vi.mocked(api).mockRejectedValue(
      new ApiError('Not enough stock for Product 1.', 422, null)
    );

    renderCart();
    await userEvent.click(screen.getByRole('button', { name: /request these/i }));

    await waitFor(() =>
      expect(screen.getByText('Not enough stock for Product 1.')).toBeInTheDocument()
    );
    // The list must survive a failed request or the user loses it.
    expect(JSON.parse(localStorage.getItem('seedle_cart'))).toHaveLength(1);
  });

  it('falls back to a generic message for a non-API failure', async () => {
    seedCart([{ product: product(1), quantity: 1 }]);
    vi.mocked(api).mockRejectedValue(new TypeError('Failed to fetch'));

    renderCart();
    await userEvent.click(screen.getByRole('button', { name: /request these/i }));

    await waitFor(() => expect(screen.getByText('Could not request those.')).toBeInTheDocument());
  });

  it('sends a signed-out visitor to log in instead of requesting', async () => {
    mockUser.mockReturnValue(null);
    seedCart([{ product: product(1), quantity: 1 }]);

    renderCart();
    await userEvent.click(screen.getByRole('button', { name: /log in to checkout/i }));

    expect(api).not.toHaveBeenCalled();
    expect(navigate).toHaveBeenCalledWith('/login', {
      state: { from: { pathname: '/cart' } },
    });
  });
});
