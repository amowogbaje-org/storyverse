import { useState } from "react";
import { useSearchParams } from "react-router-dom";
import { useStories } from "../hooks/queries/useStories";
import StoryCard from "../components/story/StoryCard";
import ViewToggle from "../components/story/ViewToggle";
import FilterBar from "../components/story/FilterBar";
import LoadingSpinner from "../components/common/LoadingSpinner";
import EmptyState from "../components/common/EmptyState";
import Container from "../components/common/Container";

export default function BrowsePage() {
  const [params] = useSearchParams();
  const [view, setView] = useState("grid");
  const [filters, setFilters] = useState({
    sort: params.get("sort") ?? undefined,
    // Homepage genre chips and other deep links land here as ?genre=/?category=,
    // so pick those up on initial load the same way "sort" already was.
    genre: params.get("genre") ?? undefined,
    category: params.get("category") ?? undefined,
  });

  const { data, isLoading } = useStories(filters);
  const stories = data?.data ?? [];

  return (
    <Container className="py-6">
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="font-display text-2xl font-semibold text-ink-950">Browse stories</h1>
        <div className="flex items-center gap-3">
          <FilterBar filters={filters} onChange={setFilters} />
          <ViewToggle view={view} onChange={setView} />
        </div>
      </div>

      {isLoading ? (
        <LoadingSpinner />
      ) : stories.length ? (
        <div className={view === "grid" ? "grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5" : "space-y-3"}>
          {stories.map((s) => (
            <StoryCard key={s.id} story={s} view={view} />
          ))}
        </div>
      ) : (
        <EmptyState title="No stories match those filters" hint="Try widening your search." />
      )}
    </Container>
  );
}
