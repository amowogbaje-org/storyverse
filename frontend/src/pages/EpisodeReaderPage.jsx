import { useEffect, useRef, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { useEpisode, useStory } from "../hooks/queries/useStories";
import { useRecordProgress } from "../hooks/mutations/useInteractions";
import { useAuth } from "../context/AuthContext";
import { canAccessEpisode, lockReason } from "../utils/access";
import { useAccessLimits } from "../hooks/queries/usePlatformStatus";
import { useReadAloud } from "../hooks/useReadAloud";
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
  const { data: storyData } = useStory(slug);
  const { data: episodeData, isLoading } = useEpisode(slug, episodeNumber);
  const recordProgress = useRecordProgress(slug, episodeNumber);
  const contentRef = useRef(null);
  const lastSent = useRef(0);
  const [autoAdvance, setAutoAdvance] = useState(false);

  const story = storyData?.data;
  const episode = episodeData?.data;

  const num = Number(episodeNumber);
  const idx = (story?.episodes ?? []).findIndex((e) => e.episode_number === num);
  const next = story?.episodes?.[idx + 1];
  const prev = story?.episodes?.[idx - 1];

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
