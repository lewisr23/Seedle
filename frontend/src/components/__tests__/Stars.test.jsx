import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import Stars from '../Stars';

const filled = (container) => container.querySelectorAll('.stars__on').length;

describe('display mode', () => {
  it('renders five stars and fills to the rounded rating', () => {
    const { container } = render(<Stars value={3} />);

    expect(container.querySelectorAll('.stars span')).toHaveLength(5);
    expect(filled(container)).toBe(3);
  });

  it('rounds a fractional average, which is what the API returns', () => {
    expect(filled(render(<Stars value={4.4} />).container)).toBe(4);
    expect(filled(render(<Stars value={4.6} />).container)).toBe(5);
  });

  it('renders nothing filled for an unrated product', () => {
    const { container } = render(<Stars value={0} />);

    expect(filled(container)).toBe(0);
  });

  it('exposes the rating to screen readers', () => {
    render(<Stars value={3} />);

    expect(screen.getByLabelText('3 out of 5')).toBeInTheDocument();
  });

  it('renders no buttons when there is no onChange, so it stays read-only', () => {
    render(<Stars value={3} />);

    expect(screen.queryAllByRole('button')).toHaveLength(0);
  });
});

describe('input mode', () => {
  it('renders buttons and reports the chosen rating', async () => {
    const onChange = vi.fn();
    render(<Stars value={0} onChange={onChange} />);

    const buttons = screen.getAllByRole('button');
    expect(buttons).toHaveLength(5);

    await userEvent.click(screen.getByLabelText('Rate 4 stars'));

    expect(onChange).toHaveBeenCalledWith(4);
  });

  it('labels a single star in the singular', () => {
    render(<Stars value={0} onChange={vi.fn()} />);

    expect(screen.getByLabelText('Rate 1 star')).toBeInTheDocument();
  });
});
