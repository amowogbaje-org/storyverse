const ICONS = {
  views: "M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z",
  likes: "M12 21s-7-4.35-9.5-8.8C.9 8.6 2.4 5 6 5c2 0 3.5 1.2 4 2.4C10.5 6.2 12 5 14 5c3.6 0 5.1 3.6 3.5 7.2C19 16.65 12 21 12 21Z",
  bookmarks: "M6 3h12v18l-6-4-6 4Z",
  comments: "M4 4h16v12H8l-4 4Z",
};

function Stat({ icon, value, label }) {
  return (
    <span className="inline-flex items-center gap-1 text-ink-500" title={label}>
      <svg viewBox="0 0 24 24" className="h-3.5 w-3.5" fill="none" stroke="currentColor" strokeWidth="1.8">
        <path d={ICONS[icon]} strokeLinecap="round" strokeLinejoin="round" />
      </svg>
      <span className="stat-num">{formatCount(value)}</span>
    </span>
  );
}

export function formatCount(n = 0) {
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
  if (n >= 1_000) return `${(n / 1_000).toFixed(1)}k`;
  return `${n}`;
}

export default function StoryStats({ story, className = "" }) {
  return (
    <div className={`flex flex-wrap items-center gap-3 ${className}`}>
      <Stat icon="views" value={story.views_count} label="Reads" />
      <Stat icon="likes" value={story.likes_count} label="Likes" />
      <Stat icon="bookmarks" value={story.bookmarks_count} label="Bookmarks" />
      <Stat icon="comments" value={story.comments_count} label="Comments" />
    </div>
  );
}
