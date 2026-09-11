const COLORS = ['--avatar-1', '--avatar-2', '--avatar-3', '--avatar-4', '--avatar-5', '--avatar-6'];

function colorFor(name) {
  let hash = 0;
  for (let i = 0; i < (name || '').length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return `var(${COLORS[Math.abs(hash) % COLORS.length]})`;
}

export default function Avatar({ name, size = 36 }) {
  const initial = (name || '?').charAt(0).toUpperCase();

  return (
    <span
      className="avatar"
      style={{
        width: size,
        height: size,
        fontSize: size * 0.42,
        background: colorFor(name || ''),
      }}
    >
      {initial}
    </span>
  );
}
