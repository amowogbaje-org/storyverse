import { useState } from "react";
import { Link } from "react-router-dom";
import { useAdminStories, useAdminPenNames } from "../../hooks/queries/useAdmin";
import { useCategories, useGenres } from "../../hooks/queries/useStories";
import { useCreateStory } from "../../hooks/mutations/useAdminMutations";
import SearchableMultiSelect from "../../components/admin/SearchableMultiSelect";
import LoadingSpinner from "../../components/common/LoadingSpinner";
import EmptyState from "../../components/common/EmptyState";

export default function AdminStoriesPage() {
  const { data, isLoading } = useAdminStories(true);
  const { data: penNamesData } = useAdminPenNames(true);
  const { data: categoriesData } = useCategories();
  const { data: genresData } = useGenres();
  const createStory = useCreateStory();
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({
    pen_name_id: "", category_ids: [], genre_ids: [], title: "", description: "", cover_image_url: "", access_type: "free",
  });
  const [error, setError] = useState(null);

  const stories = data?.data ?? [];
  const penNames = penNamesData?.data ?? [];
  const categories = categoriesData?.data ?? [];
  const genres = genresData?.data ?? [];

  function update(key, value) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  function submit(e) {
    e.preventDefault();
    setError(null);

    if (form.category_ids.length === 0) {
      setError("Pick at least one category.");
      return;
    }

    createStory.mutate(form, {
      onSuccess: () => {
        setShowForm(false);
        setForm({ pen_name_id: "", category_ids: [], genre_ids: [], title: "", description: "", cover_image_url: "", access_type: "free" });
      },
      onError: (err) => setError(err.response?.data?.error?.message || "Couldn't create the story."),
    });
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h2 className="font-display text-lg font-semibold text-ink-950">Stories</h2>
        <button
          onClick={() => setShowForm((v) => !v)}
          className="rounded-full bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50"
        >
          {showForm ? "Cancel" : "New story"}
        </button>
      </div>

      {showForm && (
        <form onSubmit={submit} className="grid gap-3 rounded-card border border-ink-950/10 bg-white/60 p-4 sm:grid-cols-2">
          <select required value={form.pen_name_id} onChange={(e) => update("pen_name_id", e.target.value)}
            className="rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm sm:col-span-2">
            <option value="">Pen name…</option>
            {penNames.map((p) => <option key={p.id} value={p.id}>{p.display_name}</option>)}
          </select>

          <SearchableMultiSelect
            label="Categories (at least one)"
            placeholder="Search categories…"
            options={categories}
            selectedIds={form.category_ids}
            onChange={(ids) => update("category_ids", ids)}
          />
          <SearchableMultiSelect
            label="Genres (optional)"
            placeholder="Search genres…"
            options={genres}
            selectedIds={form.genre_ids}
            onChange={(ids) => update("genre_ids", ids)}
          />

          <input required value={form.title} onChange={(e) => update("title", e.target.value)} placeholder="Title"
            className="rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm sm:col-span-2" />
          <input required value={form.cover_image_url} onChange={(e) => update("cover_image_url", e.target.value)} placeholder="Cover image URL"
            className="rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm sm:col-span-2" />
          <textarea required value={form.description} onChange={(e) => update("description", e.target.value)} placeholder="Description" rows={3}
            className="rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm sm:col-span-2" />
          <select value={form.access_type} onChange={(e) => update("access_type", e.target.value)}
            className="rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm">
            <option value="free">Free</option>
            <option value="premium">Premium</option>
          </select>
          {error && <p className="text-sm text-ribbon-600 sm:col-span-2">{error}</p>}
          <button type="submit" disabled={createStory.isPending}
            className="rounded-card bg-gold-500 py-2 text-sm font-semibold text-ink-950 disabled:opacity-50 sm:col-span-2">
            {createStory.isPending ? "Creating…" : "Create draft"}
          </button>
        </form>
      )}

      {isLoading ? (
        <LoadingSpinner />
      ) : stories.length ? (
        <div className="overflow-hidden rounded-card border border-ink-950/10 bg-white/60">
          <table className="w-full text-sm">
            <thead className="bg-ink-950/5 text-left text-xs uppercase text-ink-500">
              <tr><th className="px-4 py-2">Title</th><th className="px-4 py-2">Status</th><th className="px-4 py-2">Access</th><th className="px-4 py-2">Episodes</th></tr>
            </thead>
            <tbody className="divide-y divide-ink-950/8">
              {stories.map((s) => (
                <tr key={s.id}>
                  <td className="px-4 py-2"><Link to={`/admin/stories/${s.id}`} className="font-medium text-ink-950 hover:underline">{s.title}</Link></td>
                  <td className="px-4 py-2 capitalize text-ink-500">{s.status}</td>
                  <td className="px-4 py-2 capitalize text-ink-500">{s.access_type}</td>
                  <td className="px-4 py-2 stat-num">{s.episodes_count}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <EmptyState title="No stories yet" hint="Create your first one above." />
      )}
    </div>
  );
}
