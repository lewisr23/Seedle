import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import HarvestForm from '../HarvestForm';
import { ApiError, api } from '../../api/client';

vi.mock('../../api/client', async () => {
  const actual = await vi.importActual('../../api/client');
  return { ...actual, api: vi.fn() };
});

const entry = (harvests = []) => ({
  entry_id: 7,
  plant: { id: 2, name: 'Courgette', type: 'vegetable', spacing_cm: 90 },
  x_cm: 50,
  y_cm: 50,
  harvests,
});

const harvest = (extra = {}) => ({
  id: 11,
  harvested_at: '2026-07-14',
  quantity: 1.5,
  unit: 'kg',
  notes: 'Four good ones',
  ...extra,
});

beforeEach(() => {
  vi.mocked(api).mockResolvedValue({});
});

describe('HarvestForm', () => {
  it('says so plainly when nothing has been picked yet', () => {
    render(<HarvestForm bedId="3" entry={entry()} />);

    expect(screen.getByText(/nothing picked from this one yet/i)).toBeInTheDocument();
  });

  it('lists what has already been picked', () => {
    render(<HarvestForm bedId="3" entry={entry([harvest()])} />);

    expect(screen.getByText('1.5 kg')).toBeInTheDocument();
    expect(screen.getByText('Four good ones')).toBeInTheDocument();
  });

  it('shows a bare count without a unit after it', () => {
    render(<HarvestForm bedId="3" entry={entry([harvest({ quantity: 6, unit: 'count' })])} />);

    expect(screen.getByText('6')).toBeInTheDocument();
  });

  it('calls an unweighed picking what it is rather than showing a zero', () => {
    render(<HarvestForm bedId="3" entry={entry([harvest({ quantity: null, unit: null })])} />);

    expect(screen.getByText('picked')).toBeInTheDocument();
  });

  it('logs a weight against the right bed and plant', async () => {
    const onLogged = vi.fn();
    const user = userEvent.setup();
    render(<HarvestForm bedId="3" entry={entry()} onLogged={onLogged} />);

    await user.click(screen.getByRole('button', { name: /log a harvest/i }));
    await user.type(screen.getByLabelText(/how much/i), '2.25');
    await user.selectOptions(screen.getByLabelText('Unit'), 'kg');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(onLogged).toHaveBeenCalled());

    const [path, options] = vi.mocked(api).mock.calls[0];
    expect(path).toBe('/garden-beds/3/plants/7/harvests');
    expect(options.body.quantity).toBe(2.25);
    expect(options.body.unit).toBe('kg');
  });

  it('sends no unit when no quantity was given, so the log does not invent one', async () => {
    const user = userEvent.setup();
    render(<HarvestForm bedId="3" entry={entry()} onLogged={vi.fn()} />);

    await user.click(screen.getByRole('button', { name: /log a harvest/i }));
    await user.type(screen.getByLabelText(/notes/i), 'A colander full');
    await user.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(api).toHaveBeenCalled());

    const [, options] = vi.mocked(api).mock.calls[0];
    expect(options.body.quantity).toBeNull();
    expect(options.body.unit).toBeNull();
    expect(options.body.notes).toBe('A colander full');
  });

  it('shows the field error when the server rejects the date', async () => {
    vi.mocked(api).mockRejectedValueOnce(
      new ApiError('The given data was invalid.', 422, {
        harvested_at: ['You cannot log a harvest in the future.'],
      })
    );
    const user = userEvent.setup();
    render(<HarvestForm bedId="3" entry={entry()} />);

    await user.click(screen.getByRole('button', { name: /log a harvest/i }));
    await user.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText(/cannot log a harvest in the future/i)).toBeInTheDocument();
  });

  it('removes a logged harvest', async () => {
    const onRemoved = vi.fn();
    const user = userEvent.setup();
    render(<HarvestForm bedId="3" entry={entry([harvest()])} onRemoved={onRemoved} />);

    await user.click(screen.getByRole('button', { name: /remove harvest/i }));

    await waitFor(() => expect(onRemoved).toHaveBeenCalled());
    expect(api).toHaveBeenCalledWith('/harvests/11', { method: 'DELETE' });
  });
});
