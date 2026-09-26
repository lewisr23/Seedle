import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import { useAuth } from '../context/AuthContext';
import { MONTHS, MONTHS_SHORT, typeIcon } from '../utils/plants';

/**
 * The plant library turned sideways: the whole year at once, sowing windows
 * against the months they should come ready.
 *
 * Bars can wrap round the end of the year, which is not a rare edge case but
 * ordinary gardening: garlic goes in during October and comes out in June.
 */
export default function Calendar() {
  const { user } = useAuth();
  const [data, setData] = useState(null);
  const [zone, setZone] = useState(user?.hardiness_zone || '');
  const [mineOnly, setMineOnly] = useState(false);
  // Only the first response picks the default view. Without this, changing
  // the zone would keep re-ticking a box the gardener had just unticked.
  const defaulted = useRef(false);

  useEffect(() => {
    api('/sowing-calendar', { params: { zone } }).then((res) => {
      setData(res);
      if (!defaulted.current) {
        defaulted.current = true;
        setMineOnly(res.rows.some((row) => row.in_beds || row.saved));
      }
    });
  }, [zone]);

  if (!data) return <div className="page container loading">Loading the calendar...</div>;

  const rows = mineOnly ? data.rows.filter((row) => row.in_beds || row.saved) : data.rows;

  return (
    <div className="page container">
      <span className="eyebrow">Plan</span>
      <h1 className="page-title">Sowing calendar</h1>
      <p className="page-subtitle">
        When to sow, and roughly when to expect to be picking it. {MONTHS[data.month - 1]} is highlighted.
      </p>

      <div className="calendar-controls">
        <div className="field">
          <label htmlFor="calendar-zone">Hardiness zone</label>
          <input
            id="calendar-zone"
            type="number"
            min="1"
            max="13"
            value={zone}
            onChange={(event) => setZone(event.target.value)}
            placeholder="All zones"
          />
        </div>
        <label className="calendar-toggle">
          <input
            type="checkbox"
            checked={mineOnly}
            onChange={(event) => setMineOnly(event.target.checked)}
          />
          Only what I am growing or have saved
        </label>
        <div className="calendar-key">
          <span className="calendar-key__item">
            <i className="calendar-bar calendar-bar--sow" /> sow
          </span>
          <span className="calendar-key__item">
            <i className="calendar-bar calendar-bar--harvest" /> harvest
          </span>
        </div>
      </div>

      {rows.length === 0 ? (
        <div className="empty-state">
          Nothing to show. {mineOnly ? 'Untick the filter to see the whole library, or ' : ''}
          <Link to="/plants">browse the plants</Link> and save a few.
        </div>
      ) : (
        <div className="calendar-scroll">
          <table className="calendar">
            <thead>
              <tr>
                <th className="calendar__plant-head">Plant</th>
                {MONTHS.map((month, index) => (
                  <th
                    key={month}
                    className={`calendar__month${index + 1 === data.month ? ' is-now' : ''}`}
                    title={month}
                  >
                    {MONTHS_SHORT[index]}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.plant.id}>
                  <th scope="row" className="calendar__plant">
                    <Link to={`/plants/${row.plant.id}`}>
                      {typeIcon(row.plant.type)} {row.plant.name}
                    </Link>
                    {row.in_beds && <span className="calendar__tag">growing</span>}
                    {!row.in_beds && row.saved && <span className="calendar__tag calendar__tag--saved">saved</span>}
                  </th>
                  {MONTHS.map((month, index) => {
                    const monthNumber = index + 1;
                    const sow = row.sow_months.includes(monthNumber);
                    const harvest = row.harvest_months.includes(monthNumber);
                    const classes = ['calendar__cell'];
                    if (monthNumber === data.month) classes.push('is-now');

                    return (
                      <td key={month} className={classes.join(' ')}>
                        {sow && <i className="calendar-bar calendar-bar--sow" title={`Sow ${row.plant.name} in ${month}`} />}
                        {harvest && (
                          <i className="calendar-bar calendar-bar--harvest" title={`Harvest ${row.plant.name} in ${month}`} />
                        )}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
