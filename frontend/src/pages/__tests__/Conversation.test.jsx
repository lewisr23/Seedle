import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Conversation from '../Conversation';
import { ApiError, api } from '../../api/client';

vi.mock('../../api/client', async () => {
  const actual = await vi.importActual('../../api/client');
  return { ...actual, api: vi.fn() };
});

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return { ...actual, useParams: () => ({ id: '3' }) };
});

const message = (id, body, isMine) => ({
  id,
  body,
  is_mine: isMine,
  created_at: new Date().toISOString(),
  sender: { id: isMine ? 1 : 2, username: isMine ? 'alice' : 'daphne' },
});

const thread = (messages) => ({
  data: {
    id: 3,
    counterpart: { id: 2, username: 'daphne' },
    product: { id: 7, title: 'Tomato seeds' },
    messages,
  },
});

function renderIt() {
  return render(
    <MemoryRouter>
      <Conversation />
    </MemoryRouter>
  );
}

beforeEach(() => {
  vi.mocked(api).mockReset();
});

afterEach(() => {
  vi.useRealTimers();
});

describe('reading a thread', () => {
  it('renders both sides of the conversation with its product context', async () => {
    vi.mocked(api).mockResolvedValue(
      thread([message(1, 'Is this available?', true), message(2, 'Yes it is', false)])
    );

    renderIt();

    expect(await screen.findByText('Is this available?')).toBeInTheDocument();
    expect(screen.getByText('Yes it is')).toBeInTheDocument();
    expect(screen.getByText('daphne')).toBeInTheDocument();
    expect(screen.getByText(/about Tomato seeds/)).toBeInTheDocument();
  });

  it('explains a conversation that is not yours instead of rendering an empty thread', async () => {
    vi.mocked(api).mockRejectedValue(new ApiError('This action is unauthorized.', 403, null));

    renderIt();

    expect(await screen.findByText(/not yours/i)).toBeInTheDocument();
  });
});

describe('replying', () => {
  it('posts the reply and shows it straight away', async () => {
    vi.mocked(api).mockResolvedValueOnce(thread([message(1, 'Is this available?', true)]));
    renderIt();
    await screen.findByText('Is this available?');

    vi.mocked(api).mockResolvedValueOnce({ data: message(9, 'One more question', true) });
    await userEvent.type(screen.getByPlaceholderText(/write a reply/i), 'One more question');
    await userEvent.click(screen.getByRole('button', { name: /send/i }));

    await waitFor(() => expect(screen.getByText('One more question')).toBeInTheDocument());
    expect(api).toHaveBeenLastCalledWith('/conversations/3/messages', {
      method: 'POST',
      body: { body: 'One more question' },
    });
  });

  it('refuses to send whitespace', async () => {
    vi.mocked(api).mockResolvedValue(thread([message(1, 'Hi', true)]));
    renderIt();
    await screen.findByText('Hi');

    await userEvent.type(screen.getByPlaceholderText(/write a reply/i), '   ');

    expect(screen.getByRole('button', { name: /send/i })).toBeDisabled();
  });

  it('surfaces a failed send without losing the thread', async () => {
    vi.mocked(api).mockResolvedValueOnce(thread([message(1, 'Hi', true)]));
    renderIt();
    await screen.findByText('Hi');

    vi.mocked(api).mockRejectedValueOnce(new ApiError('Message too long.', 422, null));
    await userEvent.type(screen.getByPlaceholderText(/write a reply/i), 'Some reply');
    await userEvent.click(screen.getByRole('button', { name: /send/i }));

    expect(await screen.findByText('Message too long.')).toBeInTheDocument();
    expect(screen.getByText('Hi')).toBeInTheDocument();
  });
});

describe('polling for the other side', () => {
  // Driving the clock rather than waiting on it: a real 5s interval would make
  // these the slowest tests in the suite for no extra confidence.
  beforeEach(() => vi.useFakeTimers());

  it('picks up a reply that arrives without a refresh', async () => {
    vi.mocked(api).mockResolvedValue(thread([message(1, 'Is this available?', true)]));
    renderIt();
    await act(() => vi.advanceTimersByTimeAsync(0));

    expect(screen.getByText('Is this available?')).toBeInTheDocument();

    // The reply lands between polls.
    vi.mocked(api).mockResolvedValue(
      thread([message(1, 'Is this available?', true), message(2, 'Yes, still got some', false)])
    );
    await act(() => vi.advanceTimersByTimeAsync(5000));

    expect(screen.getByText('Yes, still got some')).toBeInTheDocument();
  });

  it('keeps the thread on screen when a poll fails', async () => {
    vi.mocked(api).mockResolvedValueOnce(thread([message(1, 'Hi', true)]));
    renderIt();
    await act(() => vi.advanceTimersByTimeAsync(0));
    expect(screen.getByText('Hi')).toBeInTheDocument();

    vi.mocked(api).mockRejectedValue(new TypeError('Failed to fetch'));
    await act(() => vi.advanceTimersByTimeAsync(5000));

    // A dropped poll must not blank the conversation or show a load error.
    expect(screen.getByText('Hi')).toBeInTheDocument();
    expect(screen.queryByText(/could not load/i)).not.toBeInTheDocument();
  });
});
