import { useEffect, useRef, useState } from "react";
import { useParams } from "react-router-dom";
import { useAdminStory, useAdminEpisodes } from "../../hooks/queries/useAdmin";
import { useCategories, useGenres } from "../../hooks/queries/useStories";
import { useCurrencies } from "../../hooks/queries/useCurrencies";
import {
  useUpdateStory, usePublishStory, useUnpublishStory,
  useCreateEpisode, useUpdateEpisode, usePublishEpisode,
  useUploadCoverImage,
} from "../../hooks/mutations/useAdminMutations";
import SearchableMultiSelect from "../../components/admin/SearchableMultiSelect";
import EpisodeContentEditor from "../../components/admin/EpisodeContentEditor";
import LoadingSpinner from "../../components/common/LoadingSpinner";

export default function AdminStoryEditorPage() {
  const { id } = useParams();
  const { data: storyData, isLoading } = useAdminStory(id, true);
  const { data: episodesData } = useAdminEpisodes(id, true);
  const { data: categoriesData } = useCategories();
  const { data: genresData } = useGenres();
  const updateStory = useUpdateStory(id);
  const publishStory = usePublishStory(id);
  const unpublishStory = useUnpublishStory(id);
  const createEpisode = useCreateEpisode(id);
  const updateEpisode = useUpdateEpisode(id);
  const publishEpisode = usePublishEpisode(id);
  const uploadCover = useUploadCoverImage();

  const story = storyData?.data;
  const episodes = episodesData?.data ?? [];
  const categories = categoriesData?.data ?? [];
  const genres = genresData?.data ?? [];

  const { data: currenciesData } = useCurrencies();
  const currencies = currenciesData?.data ?? [];

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [coverImageUrl, setCoverImageUrl] = useState("");
  const [coverMode, setCoverMode] = useState("url"); // "url" | "upload"
  const [coverError, setCoverError] = useState(null);
  const [categoryIds, setCategoryIds] = useState([]);
  const [genreIds, setGenreIds] = useState([]);
  const [taxonomyError, setTaxonomyError] = useState(null);
  const fileInputRef = useRef(null);

  const [accessType, setAccessType] = useState("free");
  // { USD: "9.99", NGN: "4500", ... } - string values so an author can clear
  // a field to "" without a stray 0 fighting them, converted to numbers only
  // when actually saving.
  const [prices, setPrices] = useState({});
  const [pricingError, setPricingError] = useState(null);

  const [newEpisodeTitle, setNewEpisodeTitle] = useState("");
  const [newEpisodeContent, setNewEpisodeContent] = useState("");
  const [editingEpisodeId, setEditingEpisodeId] = useState(null);
  const [editTitle, setEditTitle] = useState("");
  const [editRawContent, setEditRawContent] = useState("");
  const [editStyledContent, setEditStyledContent] = useState("");
  const [lastEpisodeSaveAction, setLastEpisodeSaveAction] = useState(null); // "raw" | "styled" | null
  const [episodeSaveError, setEpisodeSaveError] = useState(null);

  useEffect(() => {
    if (story) {
      setTitle(story.title);
      setDescription(story.description);
      setCoverImageUrl(story.cover_image_url ?? "");
      setCategoryIds((story.categories ?? []).map((c) => c.id));
      setGenreIds((story.genres ?? []).map((g) => g.id));
      setAccessType(story.access_type);
      setPrices(Object.fromEntries((story.prices ?? []).map((p) => [p.currency, String(p.amount)])));
    }
  }, [story?.id]);

  if (isLoading) return <LoadingSpinner label="Loading story" />;
  if (!story) return null;

  function saveDetails(e) {
    e.preventDefault();
    setTaxonomyError(null);

    if (categoryIds.length === 0) {
      setTaxonomyError("Pick at least one category.");
      return;
    }

    updateStory.mutate({ title, description, cover_image_url: coverImageUrl, category_ids: categoryIds, genre_ids: genreIds });
  }

  function saveAccessAndPricing(e) {
    e.preventDefault();
    setPricingError(null);

    const parsedPrices = Object.entries(prices)
      .filter(([, amount]) => amount !== "" && amount !== null)
      .map(([currency, amount]) => ({ currency, amount: Number(amount) }));

    if (parsedPrices.some((p) => Number.isNaN(p.amount) || p.amount <= 0)) {
      setPricingError("Each price needs a positive number.");
      return;
    }

    if (accessType === "premium" && parsedPrices.length === 0) {
      setPricingError("Set a price in at least one currency for a premium story.");
      return;
    }

    updateStory.mutate({ access_type: accessType, prices: parsedPrices });
  }

  async function handleFileSelected(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    setCoverError(null);
    try {
      const data = await uploadCover.mutateAsync(file);
      // Fills the field but doesn't save on its own - press "Save details" to
      // actually persist it to the story, same as editing the URL by hand.
      setCoverImageUrl(data.data.url);
    } catch (err) {
      setCoverError(err.response?.data?.error?.message || "Couldn't upload that image. Try a JPG, PNG, or WebP under 8MB.");
    } finally {
      e.target.value = "";
    }
  }

  function addEpisode(e) {
    e.preventDefault();
    if (!newEpisodeTitle.trim() || !newEpisodeContent.trim()) return;
    createEpisode.mutate(
      { title: newEpisodeTitle.trim(), content: newEpisodeContent.trim() },
      { onSuccess: () => { setNewEpisodeTitle(""); setNewEpisodeContent(""); } }
    );
  }

  function startEditingEpisode(ep) {
    setEditingEpisodeId(ep.id);
    setEditTitle(ep.title);
    // raw_content is the field going forward, but fall back to content for
    // any episode saved before that column existed (see the migration).
    setEditRawContent(ep.raw_content ?? ep.content ?? "");
    setEditStyledContent(ep.content ?? "");
    setEpisodeSaveError(null);
  }

  function saveEpisodeRaw(episodeId) {
    setLastEpisodeSaveAction("raw");
    setEpisodeSaveError(null);
    updateEpisode.mutate(
      { episodeId, title: editTitle.trim(), raw_content: editRawContent.trim() },
      {
        // The backend mirrors content = raw_content when no manual style is
        // sent alongside it (see EpisodeManagementController::update) - keep
        // the local styled-text field in sync with that so the preview
        // doesn't show stale text until the next fetch.
        onSuccess: (res) => setEditStyledContent(res.data.content),
        onError: (err) => setEpisodeSaveError(err?.response?.data?.error?.message ?? "Couldn't save."),
      }
    );
  }

  function saveEpisodeStyled(episodeId) {
    setLastEpisodeSaveAction("styled");
    setEpisodeSaveError(null);
    updateEpisode.mutate(
      { episodeId, title: editTitle.trim(), content: editStyledContent.trim() },
      { onError: (err) => setEpisodeSaveError(err?.response?.data?.error?.message ?? "Couldn't save.") }
    );
  }

  return (
    <div className="space-y-8">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-xs uppercase tracking-wide text-ink-500">
            {story.status} · {story.access_type}
          </p>
          <h2 className="font-display text-xl font-semibold text-ink-950">{story.title}</h2>
        </div>
        {story.status === "published" ? (
          <button onClick={() => unpublishStory.mutate()} className="rounded-full border border-ink-950/15 px-4 py-1.5 text-sm">
            Unpublish
          </button>
        ) : (
          <button onClick={() => publishStory.mutate()} className="rounded-full bg-gold-500 px-4 py-1.5 text-sm font-semibold text-ink-950">
            {publishStory.isPending ? "Publishing…" : "Publish story"}
          </button>
        )}
      </div>
      {publishStory.isError && (
        <p className="text-sm text-ribbon-600">
          {publishStory.error?.response?.data?.error?.message ?? "Couldn't publish."}
        </p>
      )}

      <form onSubmit={saveDetails} className="space-y-4 rounded-card border border-ink-950/10 bg-white/60 p-4">
        <div>
          <label className="mb-1 block text-xs font-medium text-ink-500">Title</label>
          <input value={title} onChange={(e) => setTitle(e.target.value)}
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-ink-500">Description</label>
          <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3}
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-ink-500">Cover image</label>
          <div className="flex gap-3">
            {coverImageUrl && (
              <img src={coverImageUrl} alt="Cover preview" loading="lazy" className="h-28 w-20 shrink-0 rounded-card border border-ink-950/10 object-cover" />
            )}
            <div className="min-w-0 flex-1 space-y-2">
              <div className="flex gap-1 rounded-full border border-ink-950/15 p-0.5 text-xs font-medium">
                <button type="button" onClick={() => setCoverMode("url")}
                  className={`flex-1 rounded-full py-1 ${coverMode === "url" ? "bg-ink-950 text-parchment-50" : "text-ink-700"}`}>
                  Image URL
                </button>
                <button type="button" onClick={() => setCoverMode("upload")}
                  className={`flex-1 rounded-full py-1 ${coverMode === "upload" ? "bg-ink-950 text-parchment-50" : "text-ink-700"}`}>
                  Upload file
                </button>
              </div>

              {coverMode === "url" ? (
                <input
                  value={coverImageUrl}
                  onChange={(e) => setCoverImageUrl(e.target.value)}
                  placeholder="https://…"
                  className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
                />
              ) : (
                <div>
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onChange={handleFileSelected}
                    disabled={uploadCover.isPending}
                    className="block w-full text-sm text-ink-700 file:mr-3 file:rounded-full file:border-0 file:bg-ink-950 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-parchment-50"
                  />
                  <p className="mt-1 text-xs text-ink-500">
                    {uploadCover.isPending ? "Uploading & optimizing…" : "JPG, PNG, or WebP, up to 8MB. We'll resize and compress it automatically."}
                  </p>
                </div>
              )}
              {coverError && <p className="text-xs text-ribbon-600">{coverError}</p>}
            </div>
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <SearchableMultiSelect
            label="Categories (at least one)"
            placeholder="Search categories…"
            options={categories}
            selectedIds={categoryIds}
            onChange={setCategoryIds}
          />
          <SearchableMultiSelect
            label="Genres (optional)"
            placeholder="Search genres…"
            options={genres}
            selectedIds={genreIds}
            onChange={setGenreIds}
          />
        </div>
        {taxonomyError && <p className="text-sm text-ribbon-600">{taxonomyError}</p>}

        <button type="submit" disabled={updateStory.isPending}
          className="rounded-card bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50 disabled:opacity-50">
          {updateStory.isPending ? "Saving…" : "Save details"}
        </button>
        {updateStory.isError && (
          <p className="text-sm text-ribbon-600">
            {updateStory.error?.response?.data?.error?.message ?? "Couldn't save."}
          </p>
        )}
      </form>

      <form onSubmit={saveAccessAndPricing} className="space-y-4 rounded-card border border-ink-950/10 bg-white/60 p-4">
        <div>
          <label className="mb-1 block text-xs font-medium text-ink-500">Access</label>
          <select value={accessType} onChange={(e) => setAccessType(e.target.value)}
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm sm:w-64">
            <option value="free">Free</option>
            <option value="premium">Premium (readers buy it)</option>
          </select>
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-ink-500">
            Price per currency {accessType === "premium" && "(at least one required)"}
          </label>
          <p className="mb-2 text-xs text-ink-500">
            A reader sees whichever of these matches their own currency; if you haven't set one for theirs, they'll see
            the USD price instead. Leave a field blank to not sell in that currency.
          </p>
          <div className="grid gap-2 sm:grid-cols-2">
            {currencies.map((c) => (
              <div key={c.code} className="flex items-center gap-2">
                <span className="w-16 shrink-0 text-xs font-medium text-ink-700">{c.code} ({c.symbol})</span>
                <input
                  type="number" min="0" step="0.01" placeholder="—"
                  value={prices[c.code] ?? ""}
                  onChange={(e) => setPrices((p) => ({ ...p, [c.code]: e.target.value }))}
                  className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-1.5 text-sm"
                />
              </div>
            ))}
          </div>
        </div>

        {pricingError && <p className="text-sm text-ribbon-600">{pricingError}</p>}
        <button type="submit" disabled={updateStory.isPending}
          className="rounded-card bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50 disabled:opacity-50">
          {updateStory.isPending ? "Saving…" : "Save access & pricing"}
        </button>
        {updateStory.isError && !pricingError && (
          <p className="text-sm text-ribbon-600">
            {updateStory.error?.response?.data?.error?.message ?? "Couldn't save."}
          </p>
        )}
      </form>

      <div>
        <h3 className="mb-3 font-display text-lg font-semibold text-ink-950">Episodes</h3>
        <ul className="mb-4 divide-y divide-ink-950/8 rounded-card border border-ink-950/10 bg-white/60">
          {episodes.map((ep) => (
            <li key={ep.id} className="px-4 py-3">
              {editingEpisodeId === ep.id ? (
                <div className="space-y-3">
                  <input value={editTitle} onChange={(e) => setEditTitle(e.target.value)}
                    className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
                  <EpisodeContentEditor
                    episode={ep}
                    rawValue={editRawContent}
                    onRawChange={setEditRawContent}
                    styledValue={editStyledContent}
                    onStyledChange={setEditStyledContent}
                    onSaveRaw={() => saveEpisodeRaw(ep.id)}
                    onSaveStyled={() => saveEpisodeStyled(ep.id)}
                    savingRaw={updateEpisode.isPending && lastEpisodeSaveAction === "raw"}
                    savingStyled={updateEpisode.isPending && lastEpisodeSaveAction === "styled"}
                    error={episodeSaveError}
                  />
                  <button type="button" onClick={() => setEditingEpisodeId(null)}
                    className="rounded-full border border-ink-950/15 px-4 py-1.5 text-xs">
                    Done
                  </button>
                </div>
              ) : (
                <div className="flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-ink-950">Ep {ep.episode_number} · {ep.title}</p>
                    <p className="text-xs text-ink-500">{ep.word_count} words · {ep.status}</p>
                  </div>
                  <div className="flex shrink-0 gap-2">
                    <button
                      onClick={() => startEditingEpisode(ep)}
                      className="rounded-full border border-ink-950/15 px-3 py-1 text-xs font-medium text-ink-700"
                    >
                      Edit
                    </button>
                    {ep.status !== "published" && (
                      <button
                        onClick={() => publishEpisode.mutate(ep.id)}
                        className="rounded-full border border-gold-500 px-3 py-1 text-xs font-medium text-gold-600"
                      >
                        Publish
                      </button>
                    )}
                  </div>
                </div>
              )}
            </li>
          ))}
          {!episodes.length && <li className="px-4 py-6 text-center text-sm text-ink-500">No episodes yet.</li>}
        </ul>

        <form onSubmit={addEpisode} className="space-y-3 rounded-card border border-ink-950/10 bg-white/60 p-4">
          <p className="text-sm font-medium text-ink-950">Add episode</p>
          <input value={newEpisodeTitle} onChange={(e) => setNewEpisodeTitle(e.target.value)} placeholder="Episode title"
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
          <div>
            <label className="mb-1 block text-xs font-medium text-ink-500">
              Raw text — no formatting needed; the AI agent styles it within ~15 minutes, or style it manually after saving
            </label>
            <textarea value={newEpisodeContent} onChange={(e) => setNewEpisodeContent(e.target.value)} placeholder="Episode content" rows={6}
              className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
          </div>
          <button type="submit" disabled={createEpisode.isPending}
            className="rounded-card bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50 disabled:opacity-50">
            {createEpisode.isPending ? "Adding…" : "Add as draft"}
          </button>
        </form>
      </div>
    </div>
  );
}
