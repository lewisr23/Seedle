import { renderHook, waitFor } from '@testing-library/react';
import { act } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthProvider, useAuth } from '../AuthContext';
import { api, getToken, setToken } from '../../api/client';

// The client is the seam: mock it so these tests cover auth state transitions
// rather than re-testing fetch.
vi.mock('../../api/client', async () => {
  const actual = await vi.importActual('../../api/client');
  return { ...actual, api: vi.fn() };
});

const alice = { id: 1, username: 'alice', name: 'Alice' };

function renderAuth() {
  return renderHook(() => useAuth(), { wrapper: AuthProvider });
}

beforeEach(() => {
  vi.mocked(api).mockReset();
});

describe('bootstrapping', () => {
  it('finishes loading with no user when no token is stored', async () => {
    const { result } = renderAuth();

    await waitFor(() => expect(result.current.loading).toBe(false));
    expect(result.current.user).toBeNull();
    // No token means no reason to ask the API who we are.
    expect(api).not.toHaveBeenCalled();
  });

  it('restores the session from a stored token', async () => {
    setToken('stored-token');
    vi.mocked(api).mockResolvedValue({ data: alice });

    const { result } = renderAuth();

    await waitFor(() => expect(result.current.loading).toBe(false));
    expect(api).toHaveBeenCalledWith('/me');
    expect(result.current.user).toEqual(alice);
  });

  it('discards a token the server rejects, so a stale token does not wedge the app', async () => {
    setToken('expired-token');
    vi.mocked(api).mockRejectedValue(new Error('401'));

    const { result } = renderAuth();

    await waitFor(() => expect(result.current.loading).toBe(false));
    expect(result.current.user).toBeNull();
    expect(getToken()).toBeNull();
  });
});

describe('login and register', () => {
  it('stores the token and user on login', async () => {
    vi.mocked(api).mockResolvedValue({ token: 'fresh-token', user: alice });
    const { result } = renderAuth();
    await waitFor(() => expect(result.current.loading).toBe(false));

    await act(async () => {
      await result.current.login('alice@example.com', 'password');
    });

    expect(api).toHaveBeenCalledWith('/login', {
      method: 'POST',
      body: { email: 'alice@example.com', password: 'password' },
    });
    expect(getToken()).toBe('fresh-token');
    expect(result.current.user).toEqual(alice);
  });

  it('leaves state untouched when login fails', async () => {
    vi.mocked(api).mockRejectedValue(new Error('Invalid credentials'));
    const { result } = renderAuth();
    await waitFor(() => expect(result.current.loading).toBe(false));

    await expect(
      act(async () => {
        await result.current.login('alice@example.com', 'wrong');
      })
    ).rejects.toThrow('Invalid credentials');

    expect(getToken()).toBeNull();
    expect(result.current.user).toBeNull();
  });

  it('signs the new account straight in on register', async () => {
    vi.mocked(api).mockResolvedValue({ token: 'new-token', user: alice });
    const { result } = renderAuth();
    await waitFor(() => expect(result.current.loading).toBe(false));

    await act(async () => {
      await result.current.register({ name: 'Alice', email: 'a@e.com', password: 'password' });
    });

    expect(getToken()).toBe('new-token');
    expect(result.current.user).toEqual(alice);
  });
});

describe('logout', () => {
  it('clears local state after a successful logout call', async () => {
    setToken('live-token');
    vi.mocked(api).mockResolvedValue({ data: alice });
    const { result } = renderAuth();
    await waitFor(() => expect(result.current.user).toEqual(alice));

    await act(async () => {
      await result.current.logout();
    });

    expect(getToken()).toBeNull();
    expect(result.current.user).toBeNull();
  });

  it('still clears local state when the logout request fails', async () => {
    setToken('live-token');
    vi.mocked(api).mockResolvedValueOnce({ data: alice });
    const { result } = renderAuth();
    await waitFor(() => expect(result.current.user).toEqual(alice));

    // An already-expired token makes /logout 401. The user still expects to be
    // logged out locally rather than stuck in a half-authenticated state.
    vi.mocked(api).mockRejectedValueOnce(new Error('401'));

    await act(async () => {
      await result.current.logout();
    });

    expect(getToken()).toBeNull();
    expect(result.current.user).toBeNull();
  });
});

describe('provider boundary', () => {
  it('throws a useful error when used outside the provider', () => {
    expect(() => renderHook(() => useAuth())).toThrow(/within AuthProvider/);
  });
});
