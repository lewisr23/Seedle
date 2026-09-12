import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MessageSeller from '../MessageSeller';
import { ApiError, api } from '../../api/client';

vi.mock('../../api/client', async () => {
  const actual = await vi.importActual('../../api/client');
  return { ...actual, api: vi.fn() };
});

const mockUser = vi.fn();
vi.mock('../../context/AuthContext', () => ({
  useAuth: () => ({ user: mockUser() }),
}));

const navigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return { ...actual, useNavigate: () => navigate };
});

const product = { id: 7, title: 'Tomato seeds', seller: { id: 99, username: 'daphne' } };

function renderIt() {
  return render(
    <MemoryRouter>
      <MessageSeller product={product} />
    </MemoryRouter>
  );
}

beforeEach(() => {
  vi.mocked(api).mockReset();
  navigate.mockReset();
  mockUser.mockReturnValue({ id: 1, username: 'alice' });
});

describe('who sees what', () => {
  it('offers to message the seller for a signed-in buyer', () => {
    renderIt();

    expect(screen.getByRole('button', { name: /message seller/i })).toBeInTheDocument();
  });

  it('renders nothing on your own listing', () => {
    mockUser.mockReturnValue({ id: 99, username: 'daphne' });

    const { container } = renderIt();

    expect(container).toBeEmptyDOMElement();
  });

  it('sends a signed-out visitor to log in, returning to the listing', async () => {
    mockUser.mockReturnValue(null);
    renderIt();

    await userEvent.click(screen.getByRole('button', { name: /log in to message seller/i }));

    expect(api).not.toHaveBeenCalled();
    expect(navigate).toHaveBeenCalledWith('/login', {
      state: { from: { pathname: '/products/7' } },
    });
  });
});

describe('sending the first message', () => {
  it('opens a composer naming the seller', async () => {
    renderIt();

    await userEvent.click(screen.getByRole('button', { name: /message seller/i }));

    expect(screen.getByLabelText(/message daphne/i)).toBeInTheDocument();
  });

  it('posts the message and hands over to the thread', async () => {
    vi.mocked(api).mockResolvedValue({ data: { id: 12 } });
    renderIt();

    await userEvent.click(screen.getByRole('button', { name: /message seller/i }));
    await userEvent.type(screen.getByLabelText(/message daphne/i), 'Is this still available?');
    await userEvent.click(screen.getByRole('button', { name: /send message/i }));

    await waitFor(() => expect(navigate).toHaveBeenCalledWith('/messages/12'));
    expect(api).toHaveBeenCalledWith('/conversations', {
      method: 'POST',
      body: { product_id: 7, body: 'Is this still available?' },
    });
  });

  it('will not send whitespace', async () => {
    renderIt();

    await userEvent.click(screen.getByRole('button', { name: /message seller/i }));
    await userEvent.type(screen.getByLabelText(/message daphne/i), '   ');

    expect(screen.getByRole('button', { name: /send message/i })).toBeDisabled();
    expect(api).not.toHaveBeenCalled();
  });

  it('surfaces a server rejection and stays on the page', async () => {
    vi.mocked(api).mockRejectedValue(
      new ApiError('You cannot start a conversation with yourself about your own listing.', 422, null)
    );
    renderIt();

    await userEvent.click(screen.getByRole('button', { name: /message seller/i }));
    await userEvent.type(screen.getByLabelText(/message daphne/i), 'Hello');
    await userEvent.click(screen.getByRole('button', { name: /send message/i }));

    await waitFor(() =>
      expect(screen.getByText(/cannot start a conversation with yourself/i)).toBeInTheDocument()
    );
    expect(navigate).not.toHaveBeenCalled();
  });

  it('can be cancelled back to the button', async () => {
    renderIt();

    await userEvent.click(screen.getByRole('button', { name: /message seller/i }));
    await userEvent.click(screen.getByRole('button', { name: /cancel/i }));

    expect(screen.getByRole('button', { name: /message seller/i })).toBeInTheDocument();
    expect(screen.queryByLabelText(/message daphne/i)).not.toBeInTheDocument();
  });
});
