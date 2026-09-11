import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { timeAgo } from '../time';

const NOW = new Date('2026-06-15T12:00:00Z');
const ago = (ms) => new Date(NOW.getTime() - ms).toISOString();

const SECOND = 1000;
const MINUTE = 60 * SECOND;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

beforeEach(() => {
  vi.useFakeTimers();
  vi.setSystemTime(NOW);
});

afterEach(() => {
  vi.useRealTimers();
});

describe('timeAgo', () => {
  it('calls anything under a minute "just now"', () => {
    expect(timeAgo(ago(0))).toBe('just now');
    expect(timeAgo(ago(59 * SECOND))).toBe('just now');
  });

  it('switches to minutes at exactly one minute', () => {
    expect(timeAgo(ago(MINUTE))).toBe('1m ago');
    expect(timeAgo(ago(59 * MINUTE))).toBe('59m ago');
  });

  it('reports hours and days', () => {
    expect(timeAgo(ago(HOUR))).toBe('1h ago');
    expect(timeAgo(ago(23 * HOUR))).toBe('23h ago');
    expect(timeAgo(ago(DAY))).toBe('1d ago');
  });

  it('reports months and years using the unit it defines them as', () => {
    expect(timeAgo(ago(30 * DAY))).toBe('1m ago');
    expect(timeAgo(ago(365 * DAY))).toBe('1y ago');
  });

  it('treats a future timestamp as "just now" rather than a negative age', () => {
    const future = new Date(NOW.getTime() + HOUR).toISOString();

    expect(timeAgo(future)).toBe('just now');
  });
});
