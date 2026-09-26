import { useRef, useState } from 'react';
import { formatCm, typeIcon } from '../utils/plants';

/** Positions snap to this, in centimetres. Fine enough to be useful, coarse
 * enough that two plants meant to line up actually do. */
const SNAP_CM = 5;

const snap = (value) => Math.round(value / SNAP_CM) * SNAP_CM;
const clamp = (value, max) => Math.max(0, Math.min(max, value));

/**
 * A garden bed drawn to scale, with the plants in it dragged into place.
 *
 * The SVG viewBox is the bed in centimetres, so everything inside is written
 * in real measurements: a plant with 45cm spacing is a circle of radius 45,
 * and no part of this file has to think in pixels. The wrapper is given the
 * bed's aspect ratio in CSS, which keeps the drawing undistorted and makes
 * converting a pointer position back into centimetres a straight division.
 */
export default function PlotCanvas({
  bed,
  entries,
  issues = [],
  selectedEntryId = null,
  onSelectEntry,
  onMove,
  pendingPlant = null,
  onPlace,
}) {
  const svgRef = useRef(null);
  const [drag, setDrag] = useState(null);

  const width = bed.width_cm;
  const length = bed.length_cm;
  const placed = entries.filter((entry) => entry.x_cm !== null && entry.y_cm !== null);

  // Entry ids that appear in any reported problem, so they can be ringed.
  const flagged = new Set(issues.flatMap((issue) => issue.entries));

  const positionOf = (entry) =>
    drag && drag.entryId === entry.entry_id ? { x: drag.x, y: drag.y } : { x: entry.x_cm, y: entry.y_cm };

  const radiusOf = (entry) => Math.max((entry.plant?.spacing_cm || 20) / 2, 9);

  /** Where in the bed, in centimetres, a pointer event landed. */
  const toPlot = (event) => {
    const rect = svgRef.current.getBoundingClientRect();
    if (!rect.width || !rect.height) return { x: 0, y: 0 };

    return {
      x: clamp(snap(((event.clientX - rect.left) / rect.width) * width), width),
      y: clamp(snap(((event.clientY - rect.top) / rect.height) * length), length),
    };
  };

  const startDrag = (event, entry) => {
    event.stopPropagation();
    event.currentTarget.setPointerCapture?.(event.pointerId);
    onSelectEntry?.(entry.entry_id);
    setDrag({ entryId: entry.entry_id, x: entry.x_cm, y: entry.y_cm, moved: false });
  };

  const continueDrag = (event) => {
    if (!drag) return;
    const { x, y } = toPlot(event);
    if (x === drag.x && y === drag.y) return;
    setDrag({ ...drag, x, y, moved: true });
  };

  const endDrag = (event) => {
    if (!drag) return;
    event.stopPropagation();
    const finished = drag;
    setDrag(null);
    // A click that never moved anywhere is a selection, not a move, and
    // sending it would write the position the plant already has.
    if (finished.moved) onMove?.(finished.entryId, finished.x, finished.y);
  };

  const clickCanvas = (event) => {
    if (!pendingPlant) {
      onSelectEntry?.(null);
      return;
    }
    const { x, y } = toPlot(event);
    onPlace?.(x, y);
  };

  // A ten centimetre grid is right for a raised bed and unreadable for an
  // allotment, so the spacing follows the size of the plot.
  const minorStep = Math.max(width, length) > 600 ? 50 : 10;
  const lines = (extent, step) =>
    Array.from({ length: Math.floor(extent / step) + 1 }, (_, i) => i * step);

  const byId = new Map(placed.map((entry) => [entry.entry_id, entry]));

  return (
    <div className="plot" style={{ aspectRatio: `${width} / ${length}` }}>
      <svg
        ref={svgRef}
        className={`plot__svg${pendingPlant ? ' plot__svg--placing' : ''}`}
        viewBox={`0 0 ${width} ${length}`}
        role="img"
        aria-label={`Plan of ${bed.name}, ${formatCm(width)} by ${formatCm(length)}`}
        onPointerMove={continueDrag}
        onPointerUp={endDrag}
        onPointerLeave={endDrag}
        onClick={clickCanvas}
      >
        <rect x="0" y="0" width={width} height={length} className="plot__soil" />

        {lines(width, minorStep).map((x) => (
          <line key={`vx${x}`} x1={x} y1="0" x2={x} y2={length} className="plot__grid" vectorEffect="non-scaling-stroke" />
        ))}
        {lines(length, minorStep).map((y) => (
          <line key={`hy${y}`} x1="0" y1={y} x2={width} y2={y} className="plot__grid" vectorEffect="non-scaling-stroke" />
        ))}
        {lines(width, 100).map((x) => (
          <line key={`vmx${x}`} x1={x} y1="0" x2={x} y2={length} className="plot__grid plot__grid--metre" vectorEffect="non-scaling-stroke" />
        ))}
        {lines(length, 100).map((y) => (
          <line key={`hmy${y}`} x1="0" y1={y} x2={width} y2={y} className="plot__grid plot__grid--metre" vectorEffect="non-scaling-stroke" />
        ))}

        {issues
          .filter((issue) => issue.entries.length === 2)
          .map((issue, index) => {
            const [a, b] = issue.entries.map((id) => byId.get(id));
            if (!a || !b) return null;
            const from = positionOf(a);
            const to = positionOf(b);

            return (
              <line
                key={`issue${index}`}
                x1={from.x}
                y1={from.y}
                x2={to.x}
                y2={to.y}
                className={`plot__link plot__link--${issue.kind}`}
                vectorEffect="non-scaling-stroke"
              />
            );
          })}

        {placed.map((entry) => {
          const { x, y } = positionOf(entry);
          const radius = radiusOf(entry);
          const selected = entry.entry_id === selectedEntryId;

          return (
            <g
              key={entry.entry_id}
              className={`plot__plant plot__plant--${entry.plant?.type || 'vegetable'}${selected ? ' is-selected' : ''}${
                flagged.has(entry.entry_id) ? ' has-issue' : ''
              }`}
              onPointerDown={(event) => startDrag(event, entry)}
              onClick={(event) => event.stopPropagation()}
            >
              <title>{`${entry.plant?.name} (needs ${formatCm(entry.plant?.spacing_cm)})`}</title>
              <circle cx={x} cy={y} r={radius} className="plot__footprint" vectorEffect="non-scaling-stroke" />
              <text x={x} y={y} className="plot__icon" fontSize={radius} dominantBaseline="central" textAnchor="middle">
                {typeIcon(entry.plant?.type)}
              </text>
              {selected && (
                <text
                  x={x}
                  y={y + radius + Math.max(radius * 0.7, 10)}
                  className="plot__label"
                  fontSize={Math.max(radius * 0.7, 10)}
                  textAnchor="middle"
                >
                  {entry.plant?.name}
                </text>
              )}
            </g>
          );
        })}
      </svg>

      <div className="plot__ruler plot__ruler--x">
        <span>0</span>
        <span>{formatCm(width)}</span>
      </div>
      <div className="plot__ruler plot__ruler--y">
        <span>0</span>
        <span>{formatCm(length)}</span>
      </div>
    </div>
  );
}
