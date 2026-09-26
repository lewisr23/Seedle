/**
 * The navbar's action icons. These were emoji, which meant every platform drew
 * them differently and none of them matched the rest of the interface. Inline
 * SVG keeps them on the page's own colour via currentColor and sizes with the
 * font, so they sit properly next to the text links.
 */

const ICONS = {
  cart: (
    <>
      <path d="M2.5 3.5h2.3l2.3 10.2a1.6 1.6 0 0 0 1.56 1.25h7.66a1.6 1.6 0 0 0 1.57-1.28L19.3 7.2H6" />
      <circle cx="9.2" cy="19.4" r="1.3" fill="currentColor" stroke="none" />
      <circle cx="17.4" cy="19.4" r="1.3" fill="currentColor" stroke="none" />
    </>
  ),
  heart: <path d="M12 20.3 4.7 13a4.6 4.6 0 0 1 6.5-6.5l.8.8.8-.8A4.6 4.6 0 0 1 19.3 13z" />,
  envelope: (
    <>
      <rect x="2.6" y="5" width="18.8" height="14" rx="2.2" />
      <path d="m3.3 6.6 8.7 6.2 8.7-6.2" />
    </>
  ),
  menu: (
    <>
      <path d="M3.8 7h16.4" />
      <path d="M3.8 12h16.4" />
      <path d="M3.8 17h16.4" />
    </>
  ),
  close: (
    <>
      <path d="M6 6l12 12" />
      <path d="M18 6 6 18" />
    </>
  ),
  bell: (
    <>
      <path d="M18 8.6a6 6 0 1 0-12 0c0 5.2-1.7 6.6-1.7 6.6h15.4S18 13.8 18 8.6z" />
      <path d="M10.3 18.6a2 2 0 0 0 3.4 0" />
    </>
  ),
};

export default function Icon({ name, size = 20, className }) {
  const shape = ICONS[name];
  if (!shape) return null;

  return (
    <svg
      className={className}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      {shape}
    </svg>
  );
}
