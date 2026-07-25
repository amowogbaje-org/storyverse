import { useState } from "react";
import { useNewReleases, usePopularStories } from "../hooks/queries/useStories";
import StoryCard from "../components/story/StoryCard";
import SectionHeader from "../components/common/SectionHeader";
import LoadingSpinner from "../components/common/LoadingSpinner";
import Container from "../components/common/Container";
import WelcomeBanner from "../components/common/WelcomeBanner";
import NextBadges from "../components/badges/NextBadges";

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

export default function HomePage() {
  const { data: newData, isLoading: newLoading } = useNewReleases();
  const { data: popularData, isLoading: popularLoading } = usePopularStories();

  return (
    <Container className="py-6">
      <WelcomeBanner />
      <NextBadges variant="compact" limit={1} />
      <section className="mb-10 overflow-hidden rounded-card bg-ink-950 px-6 py-10 text-parchment-50 sm:px-10 sm:py-14">
        <p className="font-mono text-xs uppercase tracking-widest text-gold-400">Serialized fiction, one episode at a time</p>
        <h1 className="mt-3 max-w-lg font-display text-3xl font-semibold sm:text-4xl">
          Stories worth staying up for.
        </h1>
        <p className="mt-3 max-w-md text-sm text-ink-300 sm:text-base">
          Read the first episodes free, follow your favorite authors, and pick up exactly where you left off.
        </p>
      </section>

      <section className="mb-10">
        <SectionHeader title="New releases" viewAllHref="/browse?sort=new" />
        {newLoading ? <LoadingSpinner /> : <StoryRow stories={newData?.data ?? []} />}
      </section>

      <section>
        <SectionHeader title="Popular right now" viewAllHref="/browse?sort=popular" />
        {popularLoading ? <LoadingSpinner /> : <StoryRow stories={popularData?.data ?? []} />}
      </section>
    </Container>
  );
}
