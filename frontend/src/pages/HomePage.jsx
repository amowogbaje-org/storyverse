import { useState } from "react";
import {
  useNewReleases,
  usePopularStories,
  useTrendingRanked,
  useContinueReading,
} from "../hooks/queries/useStories";
import { useGenres } from "../hooks/queries/useGenres";
import StoryCard from "../components/story/StoryCard";
import SectionHeader from "../components/common/SectionHeader";
import LoadingSpinner from "../components/common/LoadingSpinner";
import Container from "../components/common/Container";
import Seo from "../components/common/Seo";
import NextBadges from "../components/badges/NextBadges";
import { Link } from "react-router-dom";

function StoryRow({ stories }) {
  return (
    <div className="-mx-4 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-3 sm:gap-4 sm:overflow-visible sm:px-0 md:grid-cols-4 lg:grid-cols-5">
      {stories.map((s) => (
        <div key={s.id} className="w-36 shrink-0 sm:w-auto">
          <StoryCard story={s} />
        </div>
      ))}
    </div>
  );
}

// Rank matters here — this is an actual leaderboard, not decorative numbering.
function RankedRow({ stories }) {
  return (
    <div className="-mx-4 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-x-6 sm:gap-y-4 sm:overflow-visible sm:px-0 lg:grid-cols-3">
      {stories.map((s, i) => (
        <Link
          key={s.id}
          to={`/story/${s.slug ?? s.id}`}
          className="flex w-64 shrink-0 items-center gap-4 rounded-card border border-ink-800 bg-ink-900/40 p-3 transition-colors hover:border-gold-400/60 sm:w-auto"
        >
          <span className="font-display text-3xl font-semibold leading-none text-gold-400/80 tabular-nums">
            {String(i + 1).padStart(2, "0")}
          </span>
          <img
            src={s.coverUrl}
            alt=""
            className="h-16 w-12 shrink-0 rounded object-cover"
          />
          <div className="min-w-0">
            <p className="truncate font-display text-sm font-medium text-parchment-50">
              {s.title}
            </p>
            <p className="truncate text-xs text-ink-400">{s.author?.name}</p>
          </div>
        </Link>
      ))}
    </div>
  );
}

function GenreRail({ genres }) {
  if (!genres?.length) return null;
  return (
    <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:px-0">
      {genres.map((g) => (
        <Link
          key={g.id}
          to={`/browse?genre=${g.slug}`}
          className="shrink-0 rounded-full border border-ink-700 px-4 py-1.5 text-sm text-ink-200 transition-colors hover:border-gold-400 hover:text-gold-400"
        >
          {g.name}
        </Link>
      ))}
    </div>
  );
}

export default function HomePage() {
  const { data: newData, isLoading: newLoading } = useNewReleases();
  const { data: popularData, isLoading: popularLoading } = usePopularStories();
  const { data: rankedData, isLoading: rankedLoading } = useTrendingRanked({ limit: 6 });
  const { data: genresData } = useGenres();
  const { data: continueData, isLoading: continueLoading } = useContinueReading();

  const featured = popularData?.data?.[0] ?? newData?.data?.[0];
  const hasContinue = !continueLoading && (continueData?.data?.length ?? 0) > 0;

  return (
    <Container className="py-6">
      <Seo path="/" />
      <NextBadges variant="compact" limit={1} />

      {/* Hero — leads with an actual story instead of pure copy */}
      <section className="mb-10 grid gap-6 overflow-hidden rounded-card bg-ink-950 px-6 py-10 text-parchment-50 sm:px-10 sm:py-14 md:grid-cols-[1fr_auto] md:items-center">
        <div>
          <p className="text-xs text-gold-400">Serialized fiction, one episode at a time</p>
          <h1 className="mt-3 max-w-lg font-display text-3xl font-semibold sm:text-4xl">
            Stories worth staying up for.
          </h1>
          <p className="mt-3 max-w-md text-sm text-ink-300 sm:text-base">
            Read the first episodes free, follow your favorite authors, and pick up exactly where you left off.
          </p>
          {featured && (
            <Link
              to={`/story/${featured.slug ?? featured.id}`}
              className="mt-6 inline-flex items-center gap-2 rounded-full bg-gold-400 px-5 py-2.5 text-sm font-medium text-ink-950 transition-opacity hover:opacity-90"
            >
              Start reading {featured.title}
            </Link>
          )}
        </div>
        {featured?.coverUrl && (
          <img
            src={featured.coverUrl}
            alt={featured.title}
            className="mx-auto h-48 w-32 rounded-lg object-cover shadow-2xl sm:h-56 sm:w-40"
          />
        )}
      </section>

      <section className="mb-10">
        <GenreRail genres={genresData?.data} />
      </section>

      {hasContinue && (
        <section className="mb-10">
          <SectionHeader title="Pick up where you left off" viewAllHref="/library" />
          <StoryRow stories={continueData.data} />
        </section>
      )}

      <section className="mb-10">
        <SectionHeader title="Trending now" viewAllHref="/browse?sort=trending" />
        {rankedLoading ? <LoadingSpinner /> : <RankedRow stories={rankedData?.data ?? []} />}
      </section>

      <section className="mb-10">
        <SectionHeader title="New releases" viewAllHref="/browse?sort=new" />
        {newLoading ? <LoadingSpinner /> : <StoryRow stories={newData?.data ?? []} />}
      </section>

      <section className="mb-10">
        <SectionHeader title="Popular right now" viewAllHref="/browse?sort=popular" />
        {popularLoading ? <LoadingSpinner /> : <StoryRow stories={popularData?.data ?? []} />}
      </section>

      {/* Writer CTA — a second beat, distinct from the hero, aimed at the other audience */}
      <section className="mb-4 rounded-card border border-ink-800 px-6 py-8 text-center sm:px-10">
        <h2 className="font-display text-xl font-semibold text-ink-950">
          Have a story to tell?
        </h2>
        <p className="mx-auto mt-2 max-w-md text-sm text-ink-500">
          Publish your first episode today and start building an audience, one chapter at a time.
        </p>
        <Link
          to="/write"
          className="mt-5 inline-flex items-center gap-2 rounded-full border border-ink-900 px-5 py-2.5 text-sm font-medium text-ink-950 transition-colors hover:bg-ink-950 hover:text-parchment-50"
        >
          Start writing
        </Link>
      </section>
    </Container>
  );
}