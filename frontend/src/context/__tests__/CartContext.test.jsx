import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CartProvider, useCart } from '../CartContext';

const STORAGE_KEY = 'growguide_cart';

const product = (id, extra = {}) => ({
  id,
  title: `Product ${id}`,
  stock: 10,
  ...extra,
});

function renderCart() {
  return renderHook(() => useCart(), { wrapper: CartProvider });
}

describe('adding items', () => {
  it('adds a product to an empty cart', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1)));

    expect(result.current.items).toHaveLength(1);
    expect(result.current.items[0].quantity).toBe(1);
  });

  it('merges quantity instead of duplicating when the same product is added twice', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1), 2));
    act(() => result.current.addItem(product(1), 3));

    expect(result.current.items).toHaveLength(1);
    expect(result.current.items[0].quantity).toBe(5);
    expect(result.current.totalItems).toBe(5);
  });

  it('keeps different products separate, which is what makes a multi-seller cart work', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1, { seller: { id: 10 } })));
    act(() => result.current.addItem(product(2, { seller: { id: 20 } })));

    expect(result.current.items).toHaveLength(2);
  });
});

describe('changing quantities', () => {
  it('updates the quantity of one line without touching the others', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1)));
    act(() => result.current.addItem(product(2)));
    act(() => result.current.updateQuantity(1, 4));

    expect(result.current.items.find((i) => i.product.id === 1).quantity).toBe(4);
    expect(result.current.items.find((i) => i.product.id === 2).quantity).toBe(1);
  });

  it('removes the line when the quantity drops to zero', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1)));
    act(() => result.current.updateQuantity(1, 0));

    expect(result.current.items).toHaveLength(0);
  });

  it('removes the line rather than going negative', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1)));
    act(() => result.current.updateQuantity(1, -3));

    expect(result.current.items).toHaveLength(0);
  });

  it('removes and clears', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1)));
    act(() => result.current.addItem(product(2)));
    act(() => result.current.removeItem(1));
    expect(result.current.items).toHaveLength(1);

    act(() => result.current.clear());
    expect(result.current.items).toHaveLength(0);
  });
});

describe('totals', () => {

  it('sums quantities across lines for the navbar badge', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1), 2));
    act(() => result.current.addItem(product(2), 3));

    expect(result.current.totalItems).toBe(5);
  });
});

describe('persistence', () => {
  it('writes the cart to localStorage so it survives a reload', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(7), 2));

    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY));
    expect(stored).toHaveLength(1);
    expect(stored[0].product.id).toBe(7);
    expect(stored[0].quantity).toBe(2);
  });

  it('hydrates an existing cart on mount', () => {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify([{ product: product(9), quantity: 3 }])
    );

    const { result } = renderCart();

    expect(result.current.items).toHaveLength(1);
  });

  it('starts empty rather than crashing when stored JSON is corrupt', () => {
    localStorage.setItem(STORAGE_KEY, '{not json');

    const { result } = renderCart();

    expect(result.current.items).toEqual([]);
  });
});

describe('provider boundary', () => {
  it('throws a useful error when used outside the provider', () => {
    expect(() => renderHook(() => useCart())).toThrow(/within CartProvider/);
  });
});
