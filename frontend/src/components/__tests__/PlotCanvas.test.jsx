import { fireEvent, render } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import PlotCanvas from '../PlotCanvas';

const bed = { id: 1, name: 'Top bed', width_cm: 300, length_cm: 150 };

const entry = (extra = {}) => ({
  entry_id: 1,
  plant: { id: 5, name: 'Tomato', type: 'vegetable', spacing_cm: 45 },
  x_cm: 100,
  y_cm: 50,
  planted_at: null,
  ready_on: null,
  notes: null,
  ...extra,
});

/**
 * jsdom has no layout, so the SVG reports a zero-sized box and every pointer
 * position would collapse to the top-left corner. Pinning a box makes the
 * centimetre maths exercisable: 600px across a 300cm bed is 2px per cm.
 */
function renderPlot(props = {}) {
  const result = render(
    <PlotCanvas bed={bed} entries={[entry()]} issues={[]} {...props} />
  );
  const svg = result.container.querySelector('svg');
  svg.getBoundingClientRect = () => ({
    left: 0,
    top: 0,
    width: 600,
    height: 300,
    right: 600,
    bottom: 300,
    x: 0,
    y: 0,
  });

  return { ...result, svg };
}

describe('PlotCanvas', () => {
  it('draws each plant at the footprint its spacing calls for', () => {
    const { container } = renderPlot();
    const circle = container.querySelector('.plot__footprint');

    // 45cm spacing is a 22.5cm radius, in the bed's own units.
    expect(circle).toHaveAttribute('r', '22.5');
    expect(circle).toHaveAttribute('cx', '100');
    expect(circle).toHaveAttribute('cy', '50');
  });

  it('reports a drag in centimetres, snapped to the grid', () => {
    const onMove = vi.fn();
    const { container, svg } = renderPlot({ onMove });
    const plant = container.querySelector('.plot__plant');

    fireEvent.pointerDown(plant, { clientX: 200, clientY: 100, pointerId: 1 });
    fireEvent.pointerMove(svg, { clientX: 303, clientY: 98, pointerId: 1 });
    fireEvent.pointerUp(svg, { clientX: 303, clientY: 98, pointerId: 1 });

    // 303px is 151.5cm, which snaps to 150. 98px is 49cm, which snaps to 50.
    expect(onMove).toHaveBeenCalledWith(1, 150, 50);
  });

  it('treats a press that never moved as picking the plant, not moving it', () => {
    const onMove = vi.fn();
    const onSelectEntry = vi.fn();
    const { container, svg } = renderPlot({ onMove, onSelectEntry });

    fireEvent.pointerDown(container.querySelector('.plot__plant'), { clientX: 200, clientY: 100, pointerId: 1 });
    fireEvent.pointerUp(svg, { clientX: 200, clientY: 100, pointerId: 1 });

    expect(onSelectEntry).toHaveBeenCalledWith(1);
    expect(onMove).not.toHaveBeenCalled();
  });

  it('keeps a dragged plant inside the bed', () => {
    const onMove = vi.fn();
    const { container, svg } = renderPlot({ onMove });

    fireEvent.pointerDown(container.querySelector('.plot__plant'), { clientX: 200, clientY: 100, pointerId: 1 });
    // Well past the right hand edge and above the top of the bed.
    fireEvent.pointerMove(svg, { clientX: 900, clientY: -60, pointerId: 1 });
    fireEvent.pointerUp(svg, { clientX: 900, clientY: -60, pointerId: 1 });

    expect(onMove).toHaveBeenCalledWith(1, 300, 0);
  });

  it('places a pending plant where the plot is tapped', () => {
    const onPlace = vi.fn();
    const { svg } = renderPlot({
      onPlace,
      pendingPlant: { id: 9, name: 'Radish', type: 'vegetable', spacing_cm: 5 },
    });

    fireEvent.click(svg, { clientX: 44, clientY: 30 });

    expect(onPlace).toHaveBeenCalledWith(20, 15);
  });

  it('clears the selection when the bare soil is tapped', () => {
    const onSelectEntry = vi.fn();
    const { svg } = renderPlot({ onSelectEntry });

    fireEvent.click(svg, { clientX: 44, clientY: 30 });

    expect(onSelectEntry).toHaveBeenCalledWith(null);
  });

  it('rings the plants named in a problem and links the pair', () => {
    const entries = [entry(), entry({ entry_id: 2, x_cm: 130, y_cm: 50 })];
    const { container } = renderPlot({
      entries,
      issues: [{ kind: 'crowding', entries: [1, 2], note: 'Too close.' }],
    });

    expect(container.querySelectorAll('.plot__plant.has-issue')).toHaveLength(2);
    expect(container.querySelector('.plot__link--crowding')).toBeInTheDocument();
  });

  it('leaves out plants that have no place on the plan yet', () => {
    const { container } = renderPlot({
      entries: [entry(), entry({ entry_id: 3, x_cm: null, y_cm: null })],
    });

    expect(container.querySelectorAll('.plot__plant')).toHaveLength(1);
  });
});
