import EpisodeRow from "./EpisodeRow";

export default function EpisodeList({ story, episodes }) {
  return (
    <div className="divide-y divide-ink-950/8 rounded-card border border-ink-950/8 bg-white/50 px-3">
      {episodes.map((ep) => (
        <EpisodeRow key={ep.id} story={story} episode={ep} />
      ))}
    </div>
  );
}
