import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { CartProvider, useCart } from '../CartContext';

const STORAGE_KEY = 'growguide_cart';

const product = (id, price_pence, extra = {}) => ({
  id,
  title: `Product ${id}`,
  price_pence,
  price_pounds: price_pence / 100,
  stock: 10,
  ...extra,
});

function renderCart() {
  return renderHook(() => useCart(), { wrapper: CartProvider });
}

describe('adding items', () => {
  it('adds a product to an empty cart', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1, 500)));

    expect(result.current.items).toHaveLength(1);
    expect(result.current.items[0].quantity).toBe(1);
    expect(result.current.totalPence).toBe(500);
  });

  it('merges quantity instead of duplicating when the same product is added twice', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1, 500), 2));
    act(() => result.current.addItem(product(1, 500), 3));

    expect(result.current.items).toHaveLength(1);
    expect(result.current.items[0].quantity).toBe(5);
    expect(result.current.totalItems).toBe(5);
  });

  it('keeps different products separate, which is what makes a multi-seller cart work', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1, 500, { seller: { id: 10 } })));
    act(() => result.current.addItem(product(2, 250, { seller: { id: 20 } })));

    expect(result.current.items).toHaveLength(2);
    expect(result.current.totalPence).toBe(750);
  });
});

describe('changing quantities', () => {
  it('updates the quantity of one line without touching the others', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1, 500)));
    act(() => result.current.addItem(product(2, 300)));
    act(() => result.current.updateQuantity(1, 4));

    expect(result.current.items.find((i) => i.product.id === 1).quantity).toBe(4);
    expect(result.current.items.find((i) => i.product.id === 2).quantity).toBe(1);
    expect(result.current.totalPence).toBe(4 * 500 + 300);
  });

  it('removes the line when the quantity drops to zero', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1, 500)));
    act(() => result.current.updateQuantity(1, 0));

    expect(result.current.items).toHaveLength(0);
  });

  it('removes the line rather than going negative', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1, 500)));
    act(() => result.current.updateQuantity(1, -3));

    expect(result.current.items).toHaveLength(0);
    expect(result.current.totalPence).toBe(0);
  });

  it('removes and clears', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1, 500)));
    act(() => result.current.addItem(product(2, 300)));
    act(() => result.current.removeItem(1));
    expect(result.current.items).toHaveLength(1);

    act(() => result.current.clear());
    expect(result.current.items).toHaveLength(0);
    expect(result.current.totalPence).toBe(0);
  });
});

describe('totals', () => {
  it('totals in integer pence, so repeated odd prices do not drift', () => {
    const { result } = renderCart();

    // 10p three times is exactly 30p. The same sum in pounds (0.1 * 3) is
    // 0.30000000000000004, which is why prices are held as integers.
    act(() => result.current.addItem(product(1, 10), 3));

    expect(result.current.totalPence).toBe(30);
    expect(Number.isInteger(result.current.totalPence)).toBe(true);
  });

  it('sums quantities across lines for the navbar badge', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(1, 500), 2));
    act(() => result.current.addItem(product(2, 300), 3));

    expect(result.current.totalItems).toBe(5);
  });
});

describe('persistence', () => {
  it('writes the cart to localStorage so it survives a reload', () => {
    const { result } = renderCart();

    act(() => result.current.addItem(product(7, 1234), 2));

    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY));
    expect(stored).toHaveLength(1);
    expect(stored[0].product.id).toBe(7);
    expect(stored[0].quantity).toBe(2);
  });

  it('hydrates an existing cart on mount', () => {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify([{ product: product(9, 999), quantity: 3 }])
    );

    const { result } = renderCart();

    expect(result.current.items).toHaveLength(1);
    expect(result.current.totalPence).toBe(2997);
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
