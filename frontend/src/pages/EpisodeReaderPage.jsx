import { useEffect, useRef, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { useEpisode, useStory } from "../hooks/queries/useStories";
import { useRecordProgress } from "../hooks/mutations/useInteractions";
import { useAuth } from "../context/AuthContext";
import { canAccessEpisode, lockReason } from "../utils/access";
import UpsellBanner from "../components/reader/UpsellBanner";
import LoadingSpinner from "../components/common/LoadingSpinner";
import Container from "../components/common/Container";

export default function EpisodeReaderPage() {
  const { slug, episodeNumber } = useParams();
  const { user } = useAuth();
  const { data: storyData } = useStory(slug);
  const { data: episodeData, isLoading } = useEpisode(slug, episodeNumber);
  const recordProgress = useRecordProgress(slug, episodeNumber);
  const contentRef = useRef(null);
  const lastSent = useRef(0);

  const story = storyData?.data;
  const episode = episodeData?.data;

  const num = Number(episodeNumber);
  const idx = (story?.episodes ?? []).findIndex((e) => e.episode_number === num);
  const next = story?.episodes?.[idx + 1];
  const prev = story?.episodes?.[idx - 1];

  useEffect(() => {
    function onScroll() {
      const el = contentRef.current;
      if (!el) return;
      const total = el.scrollHeight - window.innerHeight;
      const scrolled = window.scrollY - el.offsetTop;
      const percent = Math.max(0, Math.min(100, (scrolled / Math.max(total, 1)) * 100));
      if (percent - lastSent.current >= 5) {
        lastSent.current = percent;
        recordProgress.mutate(Math.round(percent));
      }
    }
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug, episodeNumber]);

  if (isLoading || !story) return <LoadingSpinner label="Loading episode" />;

  if (!canAccessEpisode(story, { episode_number: num }, user)) {
    return (
      <Container className="py-10">
        <UpsellBanner reason={lockReason(story, { episode_number: num }, user)} />
      </Container>
    );
  }

  return (
    <Container className="max-w-2xl py-6 pb-24">
      <Link to={`/stories/${slug}`} className="text-sm text-teal-700 hover:underline">← {story.title}</Link>
      <h1 className="mt-2 font-display text-2xl font-semibold text-ink-950">
        Episode {episode?.episode_number} · {episode?.title}
      </h1>

      <article
        ref={contentRef}
        className="prose prose-ink mt-6 max-w-none whitespace-pre-wrap text-[17px] leading-relaxed text-ink-900"
      >
        {episode?.content}
      </article>

      <div className="mt-10 flex items-center justify-between border-t border-ink-950/10 pt-4 text-sm">
        {prev ? (
          <Link to={`/stories/${slug}/episodes/${prev.episode_number}`} className="text-teal-700 hover:underline">
            ← Episode {prev.episode_number}
          </Link>
        ) : <span />}
        {next ? (
          <Link to={`/stories/${slug}/episodes/${next.episode_number}`} className="font-medium text-teal-700 hover:underline">
            Episode {next.episode_number} →
          </Link>
        ) : <span className="text-ink-500">End of published episodes</span>}
      </div>
    </Container>
  );
}
