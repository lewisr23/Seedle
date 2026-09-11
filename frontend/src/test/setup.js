import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach, vi } from 'vitest';

// Every test starts from a clean slate: the cart and the auth token both live
// in localStorage, so leaking one test's state into the next would hide real
// bugs (and manufacture fake ones).
beforeEach(() => {
  localStorage.clear();
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});
