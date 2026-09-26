import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import { typeIcon } from '../utils/plants';

/**
 * What the year actually produced.
 *
 * Totals are grouped by plant and unit rather than plant alone, because the
 * API refuses to add two bunches to two kilos and so does this page.
 */
export default function Harvests() {
  const [summary, setSummary] = useState(null);
  const [log, setLog] = useState(null);
  const [year, setYear] = useState(new Date().getFullYear());

  useEffect(() => {
    api('/harvests/summary', { params: { year } }).then((res) => {
      setSummary(res);
      // Someone coming back in February should not be met with an empty page
      // for a year they have not started yet: fall back to their last one.
      if (res.totals.length === 0 && res.years.length > 0 && !res.years.includes(year)) {
        setYear(res.years[0]);
      }
    });
  }, [year]);

  useEffect(() => {
    api('/harvests', { params: { year } }).then((res) => setLog(res.data));
  }, [year]);

  if (!summary) return <div className="page container loading">Loading your harvests...</div>;

  const nothingEver = summary.years.length === 0;

  return (
    <div className="page container">
      <span className="eyebrow">Grow</span>
      <h1 className="page-title">Harvest log</h1>
      <p className="page-subtitle">Everything you have picked, and what it came to.</p>

      {nothingEver ? (
        <div className="empty-state">
          Nothing logged yet. Open a bed in the <Link to="/garden">Garden Planner</Link>, pick a plant on
          the plan, and log what you pick.
        </div>
      ) : (
        <>
          <div className="harvest-years">
            {summary.years.map((y) => (
              <button
                key={y}
                className={`month-chip${y === year ? ' active' : ''}`}
                onClick={() => setYear(y)}
              >
                {y}
              </button>
            ))}
          </div>

          <div className="section">
            <h2 className="section-title">{summary.year} in total</h2>
            {summary.totals.length === 0 ? (
              <div className="empty-state">Nothing logged in {summary.year}.</div>
            ) : (
              <div className="harvest-totals">
                {summary.totals.map((total, index) => (
                  <div className="harvest-total" key={`${total.plant.id}-${total.unit}-${index}`}>
                    <strong>{total.plant.name}</strong>
                    <span className="harvest-total__figure">
                      {total.quantity === null
                        ? `${total.times} pickings`
                        : `${total.quantity}${total.unit && total.unit !== 'count' ? ` ${total.unit}` : ''}`}
                    </span>
                    <span className="harvest-total__times">
                      over {total.times} {total.times === 1 ? 'picking' : 'pickings'}
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="section">
            <h2 className="section-title">Every picking</h2>
            {log === null && <div className="loading">Loading...</div>}
            {log?.length === 0 && <div className="empty-state">Nothing logged in {summary.year}.</div>}
            <ul className="harvest-log">
              {log?.map((harvest) => (
                <li key={harvest.id}>
                  <span className="harvest-log__date">
                    {new Date(harvest.harvested_at).toLocaleDateString()}
                  </span>
                  <span className="harvest-log__plant">
                    {typeIcon(harvest.plant?.type)} {harvest.plant?.name}
                  </span>
                  <span className="harvest-log__amount">
                    {harvest.quantity === null
                      ? 'picked'
                      : `${harvest.quantity}${harvest.unit && harvest.unit !== 'count' ? ` ${harvest.unit}` : ''}`}
                  </span>
                  {harvest.bed && (
                    <Link className="harvest-log__bed" to={`/garden/${harvest.bed.id}`}>
                      {harvest.bed.name}
                    </Link>
                  )}
                  {harvest.notes && <span className="harvest-log__notes">{harvest.notes}</span>}
                </li>
              ))}
            </ul>
          </div>
        </>
      )}
    </div>
  );
}
