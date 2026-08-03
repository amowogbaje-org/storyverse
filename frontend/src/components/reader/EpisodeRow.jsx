import { Link } from "react-router-dom";
import { canAccessEpisode, lockReason } from "../../utils/access";
import { useAuth } from "../../context/AuthContext";
import { useAccessLimits } from "../../hooks/queries/usePlatformStatus";
import ShareMenu from "../story/ShareMenu";

export default function EpisodeRow({ story, episode }) {
  const { user } = useAuth();
  const limits = useAccessLimits();
  const unlocked = canAccessEpisode(story, episode, user, limits);
  const reason = lockReason(story, episode, user, limits);
  const progress = episode.reader_progress_percent ?? 0;

  const body = (
    <div className="flex items-center gap-3 py-3">
      {/* the "spine" — a vertical ribbon that fills as the episode is read */}
      <div className="relative h-9 w-1.5 shrink-0 overflow-hidden rounded-full bg-ink-950/10">
        <div
          className="absolute bottom-0 w-full rounded-full bg-gold-500"
          style={{ height: `${Math.min(progress, 100)}%` }}
        />
      </div>

      <div className="min-w-0 flex-1">
        <p className={`truncate text-sm font-medium ${unlocked ? "text-ink-950" : "text-ink-300"}`}>
          Episode {episode.episode_number} · {episode.title}
        </p>
        <p className="text-xs text-ink-500">
          {unlocked
            ? progress > 0
              ? `${Math.round(progress)}% read`
              : "Not started"
            : reason === "guest_limit"
            ? "Sign in to keep reading"
            : "Premium episode"}
        </p>
      </div>

      {unlocked && (
        <div onClick={(e) => e.preventDefault()} className="shrink-0">
          <ShareMenu slug={story.slug} title={story.title} episodeNumber={episode.episode_number} variant="icon" />
        </div>
      )}

      {!unlocked && (
        <svg viewBox="0 0 24 24" className="h-4 w-4 shrink-0 text-ink-300" fill="none" stroke="currentColor" strokeWidth="1.8">
          <rect x="5" y="10" width="14" height="10" rx="2" />
          <path d="M8 10V7a4 4 0 1 1 8 0v3" strokeLinecap="round" />
        </svg>
      )}
    </div>
  );

  if (unlocked) {
    return (
      <Link to={`/stories/${story.slug}/episodes/${episode.episode_number}`} className="block px-1 hover:bg-parchment-100 rounded-lg">
        {body}
      </Link>
    );
  }

  return (
    <Link
      to={reason === "guest_limit" ? `/login?next=/stories/${story.slug}` : "/subscription"}
      className="block px-1 opacity-80 hover:bg-parchment-100 rounded-lg"
    >
      {body}
    </Link>
  );
}
