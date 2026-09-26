import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it, vi } from 'vitest';
import Harvests from '../Harvests';
import { api } from '../../api/client';

vi.mock('../../api/client', async () => {
  const actual = await vi.importActual('../../api/client');
  return { ...actual, api: vi.fn() };
});

const total = (extra = {}) => ({
  plant: { id: 1, name: 'Courgette' },
  unit: 'kg',
  quantity: 8.5,
  times: 6,
  ...extra,
});

const entry = (extra = {}) => ({
  id: 1,
  harvested_at: '2026-07-14',
  quantity: 1.5,
  unit: 'kg',
  notes: null,
  plant: { id: 1, name: 'Courgette', type: 'vegetable' },
  bed: { id: 3, name: 'Top bed' },
  ...extra,
});

/** The page asks for a summary and a log, and re-asks both when the year changes. */
function mockApi({ summaries, log = [entry()] }) {
  let call = 0;
  vi.mocked(api).mockImplementation((path) => {
    if (path === '/harvests/summary') return Promise.resolve(summaries[Math.min(call++, summaries.length - 1)]);
    if (path === '/harvests') return Promise.resolve({ data: log });
    return Promise.resolve({ data: [] });
  });
}

const renderIt = () =>
  render(
    <MemoryRouter>
      <Harvests />
    </MemoryRouter>
  );

describe('Harvests', () => {
  it('points a first-time grower at the planner rather than an empty table', async () => {
    mockApi({ summaries: [{ year: 2026, years: [], totals: [] }], log: [] });
    renderIt();

    expect(await screen.findByText(/nothing logged yet/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /garden planner/i })).toHaveAttribute('href', '/garden');
  });

  it('shows the year total and how many pickings made it up', async () => {
    mockApi({ summaries: [{ year: 2026, years: [2026], totals: [total()] }] });
    renderIt();

    expect(await screen.findByText('8.5 kg')).toBeInTheDocument();
    expect(screen.getByText(/over 6 pickings/i)).toBeInTheDocument();
  });

  it('keeps a weight and a count of the same plant as separate lines', async () => {
    mockApi({
      summaries: [
        {
          year: 2026,
          years: [2026],
          totals: [total(), total({ unit: 'count', quantity: 12, times: 3 })],
        },
      ],
    });
    renderIt();

    expect(await screen.findByText('8.5 kg')).toBeInTheDocument();
    expect(screen.getByText('12')).toBeInTheDocument();
    expect(screen.getAllByText('Courgette')).toHaveLength(2);
  });

  it('describes an unweighed run of pickings by their count', async () => {
    mockApi({ summaries: [{ year: 2026, years: [2026], totals: [total({ quantity: null, unit: null, times: 4 })] }] });
    renderIt();

    expect(await screen.findByText('4 pickings')).toBeInTheDocument();
  });

  it('lists each picking with the bed it came out of', async () => {
    mockApi({ summaries: [{ year: 2026, years: [2026], totals: [total()] }] });
    renderIt();

    expect(await screen.findByRole('link', { name: 'Top bed' })).toHaveAttribute('href', '/garden/3');
  });

  it('falls back to the last year with something in it', async () => {
    mockApi({
      summaries: [
        { year: 2026, years: [2025], totals: [] },
        { year: 2025, years: [2025], totals: [total({ quantity: 3, times: 2 })] },
      ],
    });
    renderIt();

    expect(await screen.findByText('3 kg')).toBeInTheDocument();
    await waitFor(() => expect(api).toHaveBeenCalledWith('/harvests', { params: { year: 2025 } }));
  });

  it('switches year when another one is picked', async () => {
    const user = userEvent.setup();
    mockApi({
      summaries: [
        { year: 2026, years: [2026, 2025], totals: [total()] },
        { year: 2025, years: [2026, 2025], totals: [total({ quantity: 2, times: 1 })] },
      ],
    });
    renderIt();

    await screen.findByText('8.5 kg');
    await user.click(screen.getByRole('button', { name: '2025' }));

    expect(await screen.findByText('2 kg')).toBeInTheDocument();
  });
});
