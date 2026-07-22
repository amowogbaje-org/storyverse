import { Link } from "react-router-dom";

export default function SectionHeader({ title, subtitle, viewAllHref }) {
  return (
    <div className="mb-4 flex items-end justify-between">
      <div>
        <h2 className="text-xl font-semibold text-ink-950 sm:text-2xl">{title}</h2>
        {subtitle && <p className="text-sm text-ink-500">{subtitle}</p>}
      </div>
      {viewAllHref && (
        <Link to={viewAllHref} className="text-sm font-medium text-teal-700 hover:text-teal-900">
          View all →
        </Link>
      )}
    </div>
  );
}
