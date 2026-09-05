// small outline icon set, no external dependency
const PATHS = {
  home: 'M3 11.5 12 4l9 7.5M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9',
  box: 'M21 8 12 3 3 8m18 0-9 5m9-5v9l-9 5m0-9L3 8m9 5v9M3 8v9l9 5',
  tag: 'M20.6 12.3 12.7 4.4a2 2 0 0 0-1.4-.6H5a1 1 0 0 0-1 1v6.3c0 .5.2 1 .6 1.4l7.9 7.9a2 2 0 0 0 2.8 0l5.3-5.3a2 2 0 0 0 0-2.8ZM8 8h.01',
  chart: 'M4 20V10m6 10V4m6 16v-7m6 7V8',
  users: 'M16 11a4 4 0 1 0-4-4M2 20a6 6 0 0 1 12 0M16 12a4 4 0 0 1 6 3.5V20h-4',
  logout: 'M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m6 14 5-5-5-5m5 5H9',
  plus: 'M12 5v14M5 12h14',
  x: 'M18 6 6 18M6 6l12 12',
  edit: 'm16.5 3.5 4 4L8 20H4v-4L16.5 3.5Z',
  trash: 'M4 7h16M9 7V4h6v3m-8 0 1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13',
  search: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm10 2-4.35-4.35',
  chevronDown: 'm6 9 6 6 6-6',
  filter: 'M3 5h18M6 12h12M10 19h4',
  shield: 'M12 3 4 6v6c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V6l-8-3Z',
};

export default function Icon({ name, className = 'h-5 w-5', strokeWidth = 1.8 }) {
  const d = PATHS[name];
  if (!d) return null;
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={strokeWidth}
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      <path d={d} />
    </svg>
  );
}
