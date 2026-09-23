import { useEffect, useRef, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { useQueryClient } from "@tanstack/react-query";
import { useEpisode } from "../hooks/queries/useStories";
import { useRecordProgress } from "../hooks/mutations/useInteractions";
import { useAuth } from "../context/AuthContext";
import { canAccessEpisode, lockReason } from "../utils/access";
import { useAccessLimits } from "../hooks/queries/usePlatformStatus";
import { useReadAloud } from "../hooks/useReadAloud";
import { usePrefetchOnIntent } from "../hooks/usePrefetchOnIntent";
import api from "../api/client";
import UpsellBanner from "../components/reader/UpsellBanner";
import ReadAloudBar from "../components/reader/ReadAloudBar";
import RichText from "../components/reader/RichText";
import ShareMenu from "../components/story/ShareMenu";
import Seo from "../components/common/Seo";
import LoadingSpinner from "../components/common/LoadingSpinner";
import Container from "../components/common/Container";

export default function EpisodeReaderPage() {
  const { slug, episodeNumber } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const limits = useAccessLimits();
  // The episode response now embeds everything this page needs about the
  // story (title/description/cover/access_type/episode numbers for prev-
  // next) - see EpisodeController::show's docblock. That replaces what used
  // to be a second GET /stories/{slug} call here. StoryDetailPage still
  // fetches the story on its own via useStory() - it needs the full
  // presenter payload (genres, prices, likes, per-episode lock reasons) that
  // this lighter reader-page shape deliberately leaves out, so the two
  // caches are kept separate rather than one seeding the other.
  const { data: episodeData, error: episodeError, isLoading } = useEpisode(slug, episodeNumber);
  const recordProgress = useRecordProgress(slug, episodeNumber);
  const queryClient = useQueryClient();
  const contentRef = useRef(null);
  const lastSent = useRef(0);
  const [autoAdvance, setAutoAdvance] = useState(false);

  const episode = episodeData?.data;
  // On a locked episode the request 403s (no `episode`), but the backend
  // still sends `story` alongside the error - see EpisodeController::show's
  // comment on that error() call. Falling back to it here is what lets the
  // upsell banner below render immediately instead of the page getting
  // stuck on the loading spinner (story would otherwise never arrive).
  const story = episode?.story ?? episodeError?.response?.data?.story;

  const num = Number(episodeNumber);
  const idx = (story?.episodes ?? []).findIndex((e) => e.episode_number === num);
  const next = story?.episodes?.[idx + 1];
  const prev = story?.episodes?.[idx - 1];

  // Chapter-to-chapter is THE core reading flow, so this is the highest-
  // value spot for a prefetch: no chunk to fetch (already on this page's
  // code), just getting the next/prev episode's content already sitting in
  // cache by the time the reader actually clicks "Next Episode →". Only
  // bothers prefetching what canAccessEpisode() would actually let them see -
  // no point warming a request that's just going to 403.
  const prefetchNext = usePrefetchOnIntent({
    prefetch: () =>
      next && canAccessEpisode(story, next, user, limits)
        ? queryClient.prefetchQuery({
            queryKey: ["episode", slug, String(next.episode_number)],
            queryFn: async () => (await api.get(`/stories/${slug}/episodes/${next.episode_number}`)).data,
          })
        : Promise.resolve(),
  });
  const prefetchPrev = usePrefetchOnIntent({
    prefetch: () =>
      prev && canAccessEpisode(story, prev, user, limits)
        ? queryClient.prefetchQuery({
            queryKey: ["episode", slug, String(prev.episode_number)],
            queryFn: async () => (await api.get(`/stories/${slug}/episodes/${prev.episode_number}`)).data,
          })
        : Promise.resolve(),
  });

  // "Keep reading" for read-aloud: when the utterance finishes the episode
  // and auto-advance is on, move straight to the next one - but only if the
  // reader actually has access to it, same rule the ← →  links below respect.
  const readAloud = useReadAloud(episode?.content, {
    onEnd: () => {
      if (autoAdvance && next && story && canAccessEpisode(story, next, user, limits)) {
        navigate(`/stories/${slug}/episodes/${next.episode_number}`);
      }
    },
  });

  useEffect(() => {
    let debounceTimer = null;
    let dwellTimer = null;

    function send(percent) {
      if (percent - lastSent.current < 1) return;
      lastSent.current = Math.max(lastSent.current, percent);
      recordProgress.mutate(Math.round(lastSent.current));
    }

    function onScroll() {
      const el = contentRef.current;
      if (!el) return;
      const total = el.scrollHeight - window.innerHeight;
      const scrolled = window.scrollY - el.offsetTop;
      const percent = Math.max(0, Math.min(100, (scrolled / Math.max(total, 1)) * 100));

      // Debounced rather than sent on every scroll tick: scroll fires dozens of
      // times a second, and firing a request per tick (with no guaranteed
      // response ordering) is what let a stale low-percent request land after
      // the real 100% one and silently reset it to "not started". Waiting for
      // the user to pause, plus only ever moving lastSent forward, fixes that.
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(() => send(percent), 400);
    }

    // Short episodes that fit entirely within the viewport never generate a
    // scroll event at all, so they'd otherwise get stuck at 0%/"not started"
    // forever. Count them as read once the reader has actually dwelled on the
    // page for a few seconds.
    const el = contentRef.current;
    if (el && el.scrollHeight <= window.innerHeight) {
      dwellTimer = window.setTimeout(() => send(100), 3000);
    }

    window.addEventListener("scroll", onScroll, { passive: true });
    return () => {
      window.clearTimeout(debounceTimer);
      window.clearTimeout(dwellTimer);
      window.removeEventListener("scroll", onScroll);
      // Flush whatever we have on the way out (e.g. user taps "next episode"
      // before the debounce timer would have fired) so a near-finish read
      // isn't lost.
      if (lastSent.current > 0) {
        recordProgress.mutate(Math.round(lastSent.current));
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug, episodeNumber]);

  useEffect(() => {
    readAloud.stop();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug, episodeNumber]);

  if (isLoading || !story) return <LoadingSpinner label="Loading episode" />;

  if (!canAccessEpisode(story, { episode_number: num }, user, limits)) {
    return (
      <Container className="py-10">
        <UpsellBanner
          reason={lockReason(story, { episode_number: num }, user, limits)}
          slug={slug}
          episodeNumber={num}
        />
      </Container>
    );
  }

  return (
    <Container className="max-w-2xl py-6 pb-24">
      <Seo
        title={`${story.title} — Episode ${episode?.episode_number}: ${episode?.title}`}
        description={story.description}
        image={story.cover_image_url}
        path={`/stories/${slug}/episodes/${num}`}
        type="article"
        structuredData={{
          "@context": "https://schema.org",
          "@type": "Chapter",
          isPartOf: { "@type": "Book", name: story.title },
          name: episode?.title,
          position: episode?.episode_number,
        }}
      />
      <Link to={`/stories/${slug}`} className="text-sm text-teal-700 hover:underline">← {story.title}</Link>
      <div className="mt-2 flex items-start justify-between gap-3">
        <h1 className="font-display text-2xl font-semibold text-ink-950">
          Episode {episode?.episode_number} · {episode?.title}
        </h1>
        <ShareMenu slug={slug} title={story.title} episodeNumber={num} variant="icon" />
      </div>

      <ReadAloudBar
        supported={readAloud.supported}
        speaking={readAloud.speaking}
        paused={readAloud.paused}
        onPlay={readAloud.play}
        onPause={readAloud.pause}
        onStop={readAloud.stop}
        autoAdvance={autoAdvance}
        onToggleAutoAdvance={() => setAutoAdvance((v) => !v)}
        hasNext={Boolean(next)}
      />

      <article ref={contentRef} className="mt-6">
        <RichText
          text={episode?.content}
          className="font-reading max-w-none text-[18px] leading-[1.75] text-ink-900"
        />
      </article>

      <div className="mt-10 flex items-center justify-between border-t border-ink-950/10 pt-4 text-sm">
        {prev ? (
          <Link
            to={`/stories/${slug}/episodes/${prev.episode_number}`}
            className="text-teal-700 hover:underline"
            {...prefetchPrev}
          >
            ← Episode {prev.episode_number}
          </Link>
        ) : <span />}
        {next ? (
          <Link
            to={`/stories/${slug}/episodes/${next.episode_number}`}
            className="font-medium text-teal-700 hover:underline"
            {...prefetchNext}
          >
            Episode {next.episode_number} →
          </Link>
        ) : <span className="text-ink-500">End of published episodes</span>}
      </div>
    </Container>
  );
}
