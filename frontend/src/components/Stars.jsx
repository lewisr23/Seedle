export default function Stars({ value = 0, size = 14, onChange = null }) {
  const rounded = Math.round(value);

  if (!onChange) {
    return (
      <span className="stars" style={{ fontSize: size }} aria-label={`${value} out of 5`}>
        {[1, 2, 3, 4, 5].map((n) => (
          <span key={n} className={n <= rounded ? 'stars__on' : 'stars__off'}>
            ★
          </span>
        ))}
      </span>
    );
  }

  return (
    <span className="stars stars--input" style={{ fontSize: size }}>
      {[1, 2, 3, 4, 5].map((n) => (
        <button
          key={n}
          type="button"
          className={n <= rounded ? 'stars__on' : 'stars__off'}
          onClick={() => onChange(n)}
          aria-label={`Rate ${n} star${n > 1 ? 's' : ''}`}
        >
          ★
        </button>
      ))}
    </span>
  );
}
