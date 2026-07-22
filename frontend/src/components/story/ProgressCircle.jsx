/**
 * Signature element: a wax-seal progress ring. Per spec it must stay invisible
 * until 20% of the story is covered — below that a story is "unstarted", not
 * "barely started", so we render nothing rather than a sliver of gold.
 */
export default function ProgressCircle({ percent = 0, size = 34 }) {
  if (percent < 20) return null;

  const stroke = 3;
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const offset = c - (Math.min(percent, 100) / 100) * c;

  return (
    <div
      className="relative grid place-items-center"
      style={{ width: size, height: size }}
      title={`${Math.round(percent)}% read`}
    >
      <svg width={size} height={size} className="-rotate-90">
        <circle cx={size / 2} cy={size / 2} r={r} fill="#14101F" fillOpacity="0.85" />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          fill="none"
          stroke="#3A2E1F"
          strokeWidth={stroke}
          strokeOpacity="0.4"
        />
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          fill="none"
          stroke="#D4AF56"
          strokeWidth={stroke}
          strokeDasharray={c}
          strokeDashoffset={offset}
          strokeLinecap="round"
        />
      </svg>
      <span className="absolute font-mono text-[9px] font-medium text-parchment-50">
        {Math.round(percent)}
      </span>
    </div>
  );
}
