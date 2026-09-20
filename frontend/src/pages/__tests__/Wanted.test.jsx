import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Wanted from '../Wanted';
import { ApiError, api } from '../../api/client';

vi.mock('../../api/client', async () => {
  const actual = await vi.importActual('../../api/client');
  return { ...actual, api: vi.fn() };
});

const mockUser = vi.fn();
vi.mock('../../context/AuthContext', () => ({
  useAuth: () => ({ user: mockUser() }),
}));

const want = (extra = {}) => ({
  id: 1,
  title: 'Looking for rhubarb crowns',
  description: 'Happy to collect.',
  is_open: true,
  is_mine: false,
  user: { id: 2, username: 'daphne' },
  plant: null,
  created_at: new Date().toISOString(),
  ...extra,
});

/** The page loads wants and the plant list on mount, in that order. */
function mockLoad(wants = [want()]) {
  vi.mocked(api).mockImplementation((path) => {
    if (path === '/wants') return Promise.resolve({ data: wants });
    if (path === '/plants') return Promise.resolve({ data: [{ id: 7, name: 'Rhubarb' }] });
    return Promise.resolve({ data: {} });
  });
}

const renderIt = () =>
  render(
    <MemoryRouter>
      <Wanted />
    </MemoryRouter>
  );

beforeEach(() => {
  vi.mocked(api).mockReset();
  mockUser.mockReturnValue({ id: 1, username: 'alice' });
});

describe('browsing', () => {
  it('lists open requests with who asked', async () => {
    mockLoad();
    renderIt();

    expect(await screen.findByText('Looking for rhubarb crowns')).toBeInTheDocument();
    expect(screen.getByText('daphne')).toBeInTheDocument();
    expect(screen.getByText('Happy to collect.')).toBeInTheDocument();
  });

  it('says so when nobody is asking for anything', async () => {
    mockLoad([]);
    renderIt();

    expect(await screen.findByText(/nobody is asking/i)).toBeInTheDocument();
  });

  it('hides the ask button from signed-out visitors', async () => {
    mockUser.mockReturnValue(null);
    mockLoad();
    renderIt();

    await screen.findByText('Looking for rhubarb crowns');
    expect(screen.queryByRole('button', { name: /ask for something/i })).not.toBeInTheDocument();
  });
});

describe('posting a request', () => {
  it('posts the form and reloads the list', async () => {
    mockLoad();
    renderIt();
    await screen.findByText('Looking for rhubarb crowns');

    await userEvent.click(screen.getByRole('button', { name: /ask for something/i }));
    await userEvent.type(screen.getByLabelText(/what are you after/i), 'Spare leek seed');
    await userEvent.click(screen.getByRole('button', { name: /post request/i }));

    await waitFor(() =>
      expect(api).toHaveBeenCalledWith('/wants', {
        method: 'POST',
        body: { title: 'Spare leek seed', description: '', plant_id: null },
      })
    );
  });

  it('will not post an empty title', async () => {
    mockLoad();
    renderIt();
    await screen.findByText('Looking for rhubarb crowns');

    await userEvent.click(screen.getByRole('button', { name: /ask for something/i }));

    expect(screen.getByRole('button', { name: /post request/i })).toBeDisabled();
  });

  it('shows a validation message from the server', async () => {
    mockLoad();
    renderIt();
    await screen.findByText('Looking for rhubarb crowns');

    await userEvent.click(screen.getByRole('button', { name: /ask for something/i }));
    await userEvent.type(screen.getByLabelText(/what are you after/i), 'x');

    vi.mocked(api).mockRejectedValueOnce(
      new ApiError('Invalid', 422, { title: ['That is too short.'] })
    );
    await userEvent.click(screen.getByRole('button', { name: /post request/i }));

    expect(await screen.findByText('That is too short.')).toBeInTheDocument();
  });
});

describe('managing your own request', () => {
  it('offers to mark your own open request as found', async () => {
    mockLoad([want({ is_mine: true })]);
    renderIt();
    await screen.findByText('Looking for rhubarb crowns');

    await userEvent.click(screen.getByRole('button', { name: /mark as found/i }));

    await waitFor(() => expect(api).toHaveBeenCalledWith('/wants/1/close', { method: 'PATCH' }));
  });

  it('does not offer that on somebody else request', async () => {
    mockLoad([want({ is_mine: false })]);
    renderIt();
    await screen.findByText('Looking for rhubarb crowns');

    expect(screen.queryByRole('button', { name: /mark as found/i })).not.toBeInTheDocument();
  });
});
