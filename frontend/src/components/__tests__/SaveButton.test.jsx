import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SaveButton from '../SaveButton';

const mockUser = vi.fn();
vi.mock('../../context/AuthContext', () => ({
  useAuth: () => ({ user: mockUser() }),
}));

const isSaved = vi.fn();
const toggle = vi.fn();
vi.mock('../../context/SavedContext', () => ({
  useSaved: () => ({ isSaved: isSaved(), toggle }),
}));

const navigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return { ...actual, useNavigate: () => navigate };
});

function renderIt() {
  return render(
    <MemoryRouter>
      <SaveButton type="product" id={3} returnTo="/products/3" />
    </MemoryRouter>
  );
}

beforeEach(() => {
  toggle.mockReset();
  navigate.mockReset();
  mockUser.mockReturnValue({ id: 1 });
  isSaved.mockReturnValue(() => false);
});

describe('rendering', () => {
  it('shows a hollow heart, not pressed, when unsaved', () => {
    renderIt();

    const button = screen.getByRole('button', { name: /save for later/i });
    expect(button).toHaveAttribute('aria-pressed', 'false');
    expect(button).toHaveTextContent('♡');
  });

  it('shows a filled heart, pressed, when saved', () => {
    isSaved.mockReturnValue(() => true);

    renderIt();

    const button = screen.getByRole('button', { name: /remove from saved/i });
    expect(button).toHaveAttribute('aria-pressed', 'true');
    expect(button).toHaveTextContent('♥');
  });

  it('renders unsaved for a signed-out visitor without consulting saved state', () => {
    mockUser.mockReturnValue(null);
    isSaved.mockReturnValue(() => true);

    renderIt();

    expect(screen.getByRole('button')).toHaveAttribute('aria-pressed', 'false');
  });
});

describe('clicking', () => {
  it('toggles for a signed-in user', async () => {
    renderIt();

    await userEvent.click(screen.getByRole('button'));

    expect(toggle).toHaveBeenCalledWith('product', 3);
    expect(navigate).not.toHaveBeenCalled();
  });

  it('sends a signed-out visitor to log in, returning to where they were', async () => {
    mockUser.mockReturnValue(null);
    renderIt();

    await userEvent.click(screen.getByRole('button'));

    expect(toggle).not.toHaveBeenCalled();
    expect(navigate).toHaveBeenCalledWith('/login', {
      state: { from: { pathname: '/products/3' } },
    });
  });
});
