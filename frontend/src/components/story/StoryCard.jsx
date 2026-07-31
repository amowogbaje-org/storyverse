import { Link } from "react-router-dom";
import StoryStats from "./StoryStats";
import ProgressCircle from "./ProgressCircle";

const FALLBACK_COVER =
  "data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='420'%3E%3Crect width='300' height='420' fill='%231B1230'/%3E%3C/svg%3E";

function AccessBadge({ story }) {
  if (story.access_type === "free") return null;
  return (
    <span className="absolute left-2 top-2 rounded-full bg-gold-500/95 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink-950">
      Premium
    </span>
  );
}

export default function StoryCard({ story, view = "grid" }) {
  const progress = story.reader_progress_percent ?? 0;

  if (view === "list") {
    return (
      <Link
        to={`/stories/${story.slug}`}
        className="flex gap-3 rounded-card border border-ink-950/8 bg-white/60 p-3 shadow-card transition hover:-translate-y-0.5"
      >
        <div className="relative h-24 w-16 shrink-0 overflow-hidden rounded-[6px] bg-ink-900">
          <img src={story.cover_image_url || FALLBACK_COVER} alt="" className="h-full w-full object-cover" loading="lazy" />
          <AccessBadge story={story} />
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-2">
            <h3 className="line-clamp-2 font-display text-base font-semibold text-ink-950">{story.title}</h3>
            <ProgressCircle percent={progress} size={28} />
          </div>
          <p className="mt-0.5 text-xs text-ink-500">{story.author_display_name}</p>
          <p className="mt-1 line-clamp-2 text-xs text-ink-500">{story.description}</p>
          <StoryStats story={story} className="mt-2 text-[11px]" />
        </div>
      </Link>
    );
  }

  return (
    <Link
      to={`/stories/${story.slug}`}
      className="group flex flex-col overflow-hidden rounded-card border border-ink-950/8 bg-white/60 shadow-card transition hover:-translate-y-0.5"
    >
      <div className="relative aspect-[3/4] w-full overflow-hidden bg-ink-900">
        <img
          src={story.cover_image_url || FALLBACK_COVER}
          alt={story.title}
          loading="lazy"
          decoding="async"
          className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
        />
        <AccessBadge story={story} />
        {progress >= 20 && (
          <div className="absolute bottom-2 right-2">
            <ProgressCircle percent={progress} />
          </div>
        )}
      </div>
      <div className="flex flex-1 flex-col gap-1 p-3">
        <h3 className="line-clamp-2 font-display text-sm font-semibold leading-snug text-ink-950">{story.title}</h3>
        <p className="text-xs text-ink-500">{story.author_display_name}</p>
        <p className="line-clamp-2 text-xs text-ink-500">{story.description}</p>
        <StoryStats story={story} className="mt-auto pt-2 text-[11px]" />
      </div>
    </Link>
  );
}
