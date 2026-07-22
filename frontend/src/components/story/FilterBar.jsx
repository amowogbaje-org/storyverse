import { useCategories, useGenres } from "../../hooks/queries/useStories";

export default function FilterBar({ filters, onChange }) {
  const { data: categoriesData } = useCategories();
  const { data: genresData } = useGenres();
  const categories = categoriesData?.data ?? [];
  const genres = genresData?.data ?? [];

  function set(key, value) {
    onChange({ ...filters, [key]: value || undefined });
  }

  return (
    <div className="flex flex-wrap gap-2">
      <select
        value={filters.category ?? ""}
        onChange={(e) => set("category", e.target.value)}
        className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs"
      >
        <option value="">All categories</option>
        {categories.map((c) => (
          <option key={c.slug} value={c.slug}>{c.name}</option>
        ))}
      </select>

      <select
        value={filters.genre ?? ""}
        onChange={(e) => set("genre", e.target.value)}
        className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs"
      >
        <option value="">All genres</option>
        {genres.map((g) => (
          <option key={g.slug} value={g.slug}>{g.name}</option>
        ))}
      </select>

      <select
        value={filters.sort ?? ""}
        onChange={(e) => set("sort", e.target.value)}
        className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs"
      >
        <option value="">Sort: relevance</option>
        <option value="new">Newest</option>
        <option value="popular">Most popular</option>
      </select>
    </div>
  );
}
