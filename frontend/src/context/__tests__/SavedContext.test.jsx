import { act, renderHook, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SavedProvider, useSaved } from '../SavedContext';
import { api } from '../../api/client';

vi.mock('../../api/client', async () => {
  const actual = await vi.importActual('../../api/client');
  return { ...actual, api: vi.fn() };
});

const mockUser = vi.fn();
vi.mock('../AuthContext', () => ({
  useAuth: () => ({ user: mockUser() }),
}));

const renderSaved = () => renderHook(() => useSaved(), { wrapper: SavedProvider });

beforeEach(() => {
  vi.mocked(api).mockReset();
  mockUser.mockReturnValue({ id: 1, username: 'alice' });
});

describe('loading', () => {
  it('hydrates saved ids for a signed-in user', async () => {
    vi.mocked(api).mockResolvedValue({ product_ids: [4, 9], plant_ids: [2] });

    const { result } = renderSaved();

    await waitFor(() => expect(result.current.isSaved('product', 4)).toBe(true));
    expect(result.current.isSaved('product', 9)).toBe(true);
    expect(result.current.isSaved('plant', 2)).toBe(true);
    expect(result.current.isSaved('product', 99)).toBe(false);
    expect(result.current.savedCount).toBe(3);
  });

  it('asks for nothing when signed out', async () => {
    mockUser.mockReturnValue(null);

    const { result } = renderSaved();

    await waitFor(() => expect(result.current.savedCount).toBe(0));
    expect(api).not.toHaveBeenCalled();
  });

  it('stays usable when the initial load fails', async () => {
    vi.mocked(api).mockRejectedValue(new Error('network'));

    const { result } = renderSaved();

    await waitFor(() => expect(result.current.savedCount).toBe(0));
    expect(result.current.isSaved('product', 1)).toBe(false);
  });

  it('keeps products and plants apart despite sharing a table', async () => {
    vi.mocked(api).mockResolvedValue({ product_ids: [5], plant_ids: [7] });

    const { result } = renderSaved();

    await waitFor(() => expect(result.current.isSaved('product', 5)).toBe(true));
    expect(result.current.isSaved('plant', 5)).toBe(false);
    expect(result.current.isSaved('product', 7)).toBe(false);
  });
});

describe('toggling', () => {
  it('saves an unsaved product and posts to the right path', async () => {
    vi.mocked(api).mockResolvedValueOnce({ product_ids: [], plant_ids: [] });
    const { result } = renderSaved();
    await waitFor(() => expect(result.current.savedCount).toBe(0));

    vi.mocked(api).mockResolvedValueOnce({ saved: true });
    await act(() => result.current.toggle('product', 3));

    expect(result.current.isSaved('product', 3)).toBe(true);
    expect(api).toHaveBeenLastCalledWith('/products/3/save', { method: 'POST' });
  });

  it('unsaves a saved plant with a DELETE', async () => {
    vi.mocked(api).mockResolvedValueOnce({ product_ids: [], plant_ids: [8] });
    const { result } = renderSaved();
    await waitFor(() => expect(result.current.isSaved('plant', 8)).toBe(true));

    vi.mocked(api).mockResolvedValueOnce({ saved: false });
    await act(() => result.current.toggle('plant', 8));

    expect(result.current.isSaved('plant', 8)).toBe(false);
    expect(api).toHaveBeenLastCalledWith('/plants/8/save', { method: 'DELETE' });
  });

  it('rolls the heart back when the request fails', async () => {
    vi.mocked(api).mockResolvedValueOnce({ product_ids: [], plant_ids: [] });
    const { result } = renderSaved();
    await waitFor(() => expect(result.current.savedCount).toBe(0));

    vi.mocked(api).mockRejectedValueOnce(new Error('offline'));
    await act(() => result.current.toggle('product', 3));

    // Optimistic flip must not survive a failed save.
    expect(result.current.isSaved('product', 3)).toBe(false);
    expect(result.current.savedCount).toBe(0);
  });

  it('rolls an unsave back too, rather than losing the item locally', async () => {
    vi.mocked(api).mockResolvedValueOnce({ product_ids: [6], plant_ids: [] });
    const { result } = renderSaved();
    await waitFor(() => expect(result.current.isSaved('product', 6)).toBe(true));

    vi.mocked(api).mockRejectedValueOnce(new Error('offline'));
    await act(() => result.current.toggle('product', 6));

    expect(result.current.isSaved('product', 6)).toBe(true);
  });
});

describe('provider boundary', () => {
  it('throws a useful error when used outside the provider', () => {
    expect(() => renderHook(() => useSaved())).toThrow(/within SavedProvider/);
  });
});
