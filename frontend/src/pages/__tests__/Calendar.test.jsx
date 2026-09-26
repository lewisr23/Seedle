import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Calendar from '../Calendar';
import { api } from '../../api/client';

vi.mock('../../api/client', async () => {
  const actual = await vi.importActual('../../api/client');
  return { ...actual, api: vi.fn() };
});

const mockUser = vi.fn();
vi.mock('../../context/AuthContext', () => ({
  useAuth: () => ({ user: mockUser() }),
}));

const row = (extra = {}) => ({
  plant: { id: 1, name: 'Tomato', type: 'vegetable', days_to_maturity: 75, spacing_cm: 45 },
  sow_months: [5, 6],
  harvest_months: [8, 9],
  suitable_for_zone: true,
  saved: false,
  in_beds: false,
  plantings: [],
  ...extra,
});

// Garlic is the case the whole calendar has to get right: in during October,
// out the following June.
const garlic = row({
  plant: { id: 2, name: 'Garlic', type: 'vegetable', days_to_maturity: 240, spacing_cm: 15 },
  sow_months: [10, 11],
  harvest_months: [6, 7],
});

const mockCalendar = (rows, month = 9) => {
  vi.mocked(api).mockResolvedValue({ zone: null, month, rows });
};

const renderIt = () =>
  render(
    <MemoryRouter>
      <Calendar />
    </MemoryRouter>
  );

const cellsFor = (name) => {
  const heading = screen.getByRole('rowheader', { name: new RegExp(name, 'i') });
  return within(heading.closest('tr')).getAllByRole('cell');
};

beforeEach(() => {
  mockUser.mockReturnValue(null);
});

describe('Calendar', () => {
  it('puts a sowing bar in every month the plant goes in', async () => {
    mockCalendar([row()]);
    renderIt();

    await screen.findByRole('rowheader', { name: /tomato/i });
    const cells = cellsFor('Tomato');

    expect(cells).toHaveLength(12);
    expect(cells[4].querySelector('.calendar-bar--sow')).toBeInTheDocument();
    expect(cells[5].querySelector('.calendar-bar--sow')).toBeInTheDocument();
    expect(cells[6].querySelector('.calendar-bar--sow')).toBeNull();
  });

  it('puts a harvest bar in the months it should be ready', async () => {
    mockCalendar([row()]);
    renderIt();

    await screen.findByRole('rowheader', { name: /tomato/i });
    const cells = cellsFor('Tomato');

    expect(cells[7].querySelector('.calendar-bar--harvest')).toBeInTheDocument();
    expect(cells[8].querySelector('.calendar-bar--harvest')).toBeInTheDocument();
  });

  it('shows a crop that overwinters as sown late and picked early, not off the end of the year', async () => {
    mockCalendar([garlic]);
    renderIt();

    await screen.findByRole('rowheader', { name: /garlic/i });
    const cells = cellsFor('Garlic');

    expect(cells[9].querySelector('.calendar-bar--sow')).toBeInTheDocument();
    expect(cells[5].querySelector('.calendar-bar--harvest')).toBeInTheDocument();
    expect(cells[5].querySelector('.calendar-bar--sow')).toBeNull();
  });

  it('marks the month it is now', async () => {
    mockCalendar([row()], 3);
    renderIt();

    await screen.findByRole('rowheader', { name: /tomato/i });
    const cells = cellsFor('Tomato');

    expect(cells[2].className).toContain('is-now');
    expect(cells[3].className).not.toContain('is-now');
  });

  it('starts on what you are growing when you have something in a bed', async () => {
    mockCalendar([row({ in_beds: true }), row({ plant: { id: 3, name: 'Radish', type: 'vegetable' } })]);
    renderIt();

    await screen.findByRole('rowheader', { name: /tomato/i });

    expect(screen.getByText('growing')).toBeInTheDocument();
    expect(screen.queryByRole('rowheader', { name: /radish/i })).not.toBeInTheDocument();
  });

  it('shows the whole library once the filter is unticked', async () => {
    const user = userEvent.setup();
    mockCalendar([row({ in_beds: true }), row({ plant: { id: 3, name: 'Radish', type: 'vegetable' } })]);
    renderIt();

    await screen.findByRole('rowheader', { name: /tomato/i });
    await user.click(screen.getByRole('checkbox'));

    expect(screen.getByRole('rowheader', { name: /radish/i })).toBeInTheDocument();
  });

  it('shows the whole library from the start to someone growing nothing', async () => {
    mockCalendar([row(), row({ plant: { id: 3, name: 'Radish', type: 'vegetable' } })]);
    renderIt();

    await screen.findByRole('rowheader', { name: /radish/i });
    expect(screen.getByRole('checkbox')).not.toBeChecked();
  });

  it('asks the API again when the zone changes', async () => {
    const user = userEvent.setup();
    mockCalendar([row()]);
    renderIt();

    await screen.findByRole('rowheader', { name: /tomato/i });
    await user.type(screen.getByLabelText(/hardiness zone/i), '8');

    await waitFor(() =>
      expect(api).toHaveBeenLastCalledWith('/sowing-calendar', { params: { zone: '8' } })
    );
  });
});
