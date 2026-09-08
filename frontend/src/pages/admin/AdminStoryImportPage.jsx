import { useState } from "react";
import { Link } from "react-router-dom";
import { useAdminPenNames } from "../../hooks/queries/useAdmin";
import { useCategories, useGenres } from "../../hooks/queries/useStories";
import {
  usePreviewStoryImport,
  useConfirmStoryImport,
  useDownloadStoryImportTemplate,
} from "../../hooks/mutations/useAdminMutations";
import SearchableMultiSelect from "../../components/admin/SearchableMultiSelect";
import LoadingSpinner from "../../components/common/LoadingSpinner";

function IssueList({ items, tone }) {
  if (!items?.length) return null;
  const styles = tone === "error"
    ? "border-ribbon-500/30 bg-ribbon-500/10 text-ribbon-600"
    : "border-gold-500/40 bg-gold-400/10 text-gold-600";
  return (
    <ul className={`space-y-1 rounded-card border p-3 text-xs ${styles}`}>
      {items.map((msg, i) => (
        <li key={i} className="flex gap-1.5">
          <span aria-hidden="true">{tone === "error" ? "✕" : "⚠"}</span>
          <span className={tone === "error" ? "text-ribbon-700" : "text-ink-700"}>{msg}</span>
        </li>
      ))}
    </ul>
  );
}

export default function AdminStoryImportPage() {
  const { data: penNamesData } = useAdminPenNames(true);
  const { data: categoriesData } = useCategories();
  const { data: genresData } = useGenres();
  const preview = usePreviewStoryImport();
  const confirmImport = useConfirmStoryImport();
  const downloadTemplate = useDownloadStoryImportTemplate();

  const penNames = penNamesData?.data ?? [];
  const categories = categoriesData?.data ?? [];
  const genres = genresData?.data ?? [];

  const [step, setStep] = useState("upload"); // upload | review | done
  const [penNameId, setPenNameId] = useState("");
  const [file, setFile] = useState(null);
  const [uploadError, setUploadError] = useState(null);

  const [review, setReview] = useState(null); // raw preview response, kept for episodes/title fallback
  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [categoryIds, setCategoryIds] = useState([]);
  const [genreIds, setGenreIds] = useState([]);
  const [pendingNewGenres, setPendingNewGenres] = useState([]); // unmatched tokens the author kept
  const [target, setTarget] = useState("new"); // "new" | storyId as string
  const [result, setResult] = useState(null);

  function resetAll() {
    setStep("upload");
    setFile(null);
    setUploadError(null);
    setReview(null);
    setResult(null);
  }

  function handlePreview(e) {
    e.preventDefault();
    setUploadError(null);

    if (!penNameId) {
      setUploadError("Choose which pen name this story belongs to first.");
      return;
    }
    if (!file) {
      setUploadError("Choose a .md file to upload.");
      return;
    }

    preview.mutate(
      { file, pen_name_id: penNameId },
      {
        onSuccess: ({ data }) => {
          setReview(data);
          setTitle(data.title || "");
          setDescription(data.description || "");
          setCategoryIds(data.matched_category_ids || []);
          setGenreIds(data.matched_genre_ids || []);
          setPendingNewGenres(data.unmatched_genre_tokens || []);
          const bestExisting = data.suggested_existing_stories?.[0];
          setTarget(bestExisting && bestExisting.title_similarity >= 0.6 ? String(bestExisting.id) : "new");
          setStep("review");
        },
        onError: (err) => setUploadError(err.response?.data?.error?.message || "Couldn't read that file."),
      }
    );
  }

  function handleConfirm() {
    const payload = {
      pen_name_id: penNameId,
      episodes: review.episodes.map((ep) => ({ title: ep.title, content: ep.content })),
    };

    if (target === "new") {
      payload.title = title;
      payload.description = description;
      payload.category_ids = categoryIds;
      payload.genre_ids = genreIds;
      payload.new_genre_names = pendingNewGenres;
    } else {
      // Attaching to an existing story only adds episodes - it never
      // touches that story's own categories/genres, so the matched values
      // from this file (which the review screen doesn't even show once
      // "existing" is picked) are deliberately left out here.
      payload.story_id = target;
    }

    confirmImport.mutate(payload, {
      onSuccess: ({ data }) => {
        setResult(data);
        setStep("done");
      },
    });
  }

  const existingOptions = review?.suggested_existing_stories ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="font-display text-lg font-semibold text-ink-950">Bulk import</h2>
          <p className="text-sm text-ink-500">Upload a manuscript and get every episode created as a draft in one go.</p>
        </div>
        <button
          type="button"
          onClick={() => downloadTemplate.mutate()}
          disabled={downloadTemplate.isPending}
          className="rounded-full border border-ink-950/15 bg-white px-4 py-2 text-sm font-medium text-ink-700 hover:border-gold-500 disabled:opacity-50"
        >
          {downloadTemplate.isPending ? "Preparing…" : "Download template"}
        </button>
      </div>

      {step === "upload" && (
        <form onSubmit={handlePreview} className="space-y-4 rounded-card border border-ink-950/10 bg-white/60 p-4">
          <div>
            <label className="mb-1 block text-xs font-medium text-ink-500">Pen name</label>
            <select
              required
              value={penNameId}
              onChange={(e) => setPenNameId(e.target.value)}
              className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm sm:max-w-xs"
            >
              <option value="">Pen name…</option>
              {penNames.map((p) => (
                <option key={p.id} value={p.id}>{p.display_name}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="mb-1 block text-xs font-medium text-ink-500">Manuscript file (.md, .markdown, or .txt)</label>
            <input
              type="file"
              accept=".md,.markdown,.txt"
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
              className="block w-full text-sm text-ink-700 file:mr-3 file:rounded-full file:border-0 file:bg-ink-950 file:px-4 file:py-2 file:text-sm file:font-medium file:text-parchment-50"
            />
          </div>

          {uploadError && <p className="text-sm text-ribbon-600">{uploadError}</p>}

          <button
            type="submit"
            disabled={preview.isPending}
            className="rounded-card bg-gold-500 px-5 py-2 text-sm font-semibold text-ink-950 disabled:opacity-50"
          >
            {preview.isPending ? "Reading file…" : "Preview import"}
          </button>
        </form>
      )}

      {step === "review" && review && (
        <div className="space-y-5">
          <IssueList items={review.errors} tone="error" />
          <IssueList items={review.warnings} tone="warning" />

          <div className="rounded-card border border-ink-950/10 bg-white/60 p-4">
            <p className="text-xs font-medium text-ink-500">
              {review.episode_count} episode{review.episode_count === 1 ? "" : "s"} found · {review.total_word_count.toLocaleString()} words total
            </p>
          </div>

          <div className="rounded-card border border-ink-950/10 bg-white/60 p-4">
            <p className="mb-2 text-sm font-semibold text-ink-950">Where should these episodes go?</p>
            <div className="space-y-2">
              <label className="flex items-center gap-2 text-sm text-ink-700">
                <input type="radio" checked={target === "new"} onChange={() => setTarget("new")} />
                Create a new story
              </label>
              {existingOptions.map((s) => (
                <label key={s.id} className="flex items-center gap-2 text-sm text-ink-700">
                  <input type="radio" checked={target === String(s.id)} onChange={() => setTarget(String(s.id))} />
                  Add to existing: <span className="font-medium text-ink-950">{s.title}</span>
                  {s.title_similarity >= 0.6 && (
                    <span className="rounded-full bg-gold-400/20 px-2 py-0.5 text-[11px] font-medium text-gold-600">
                      looks like a match
                    </span>
                  )}
                </label>
              ))}
            </div>
          </div>

          {target === "new" && (
            <div className="grid gap-3 rounded-card border border-ink-950/10 bg-white/60 p-4 sm:grid-cols-2">
              <input
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Title"
                className="rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm sm:col-span-2"
              />
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                placeholder="Description"
                rows={3}
                className="rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm sm:col-span-2"
              />
              <p className="text-xs text-ink-500 sm:col-span-2">
                Cover starts as a placeholder — you can swap in a real one from the story's editor page after import.
              </p>

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

              {pendingNewGenres.length > 0 && (
                <div className="sm:col-span-2">
                  <p className="mb-1.5 text-xs font-medium text-ink-500">
                    Not found in your existing genres — these will be added as new genre tags:
                  </p>
                  <div className="flex flex-wrap gap-1.5">
                    {pendingNewGenres.map((name) => (
                      <button
                        key={name}
                        type="button"
                        onClick={() => setPendingNewGenres((list) => list.filter((n) => n !== name))}
                        className="flex items-center gap-1 rounded-full border border-gold-500/40 bg-gold-400/10 px-2.5 py-1 text-xs font-medium text-gold-600"
                        title="Remove — this tag won't be created"
                      >
                        {name}
                        <span aria-hidden="true">×</span>
                      </button>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}

          <div className="rounded-card border border-ink-950/10 bg-white/60">
            <p className="border-b border-ink-950/10 px-4 py-2 text-sm font-semibold text-ink-950">Episodes</p>
            <div className="max-h-80 divide-y divide-ink-950/8 overflow-y-auto">
              {review.episodes.map((ep) => (
                <div key={ep.number} className="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                  <span className="text-ink-700">
                    <span className="text-ink-400">{ep.number}.</span> {ep.title}
                  </span>
                  <span className="shrink-0 stat-num text-xs text-ink-500">{ep.word_count.toLocaleString()} words</span>
                </div>
              ))}
            </div>
          </div>

          {confirmImport.isError && (
            <p className="text-sm text-ribbon-600">
              {confirmImport.error?.response?.data?.error?.message || "Couldn't import — please try again."}
            </p>
          )}

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={resetAll}
              className="rounded-full border border-ink-950/15 px-4 py-2 text-sm font-medium text-ink-700"
            >
              Start over
            </button>
            <button
              type="button"
              onClick={handleConfirm}
              disabled={!review.valid || confirmImport.isPending || (target === "new" && categoryIds.length === 0)}
              className="rounded-full bg-gold-500 px-5 py-2 text-sm font-semibold text-ink-950 disabled:opacity-50"
            >
              {confirmImport.isPending
                ? "Importing…"
                : `Import ${review.episode_count} episode${review.episode_count === 1 ? "" : "s"}`}
            </button>
            {target === "new" && categoryIds.length === 0 && (
              <span className="text-xs text-ink-500">Pick at least one category first.</span>
            )}
          </div>
        </div>
      )}

      {step === "done" && result && (
        <div className="space-y-4 rounded-card border border-ink-950/10 bg-white/60 p-6 text-center">
          <p className="font-display text-lg font-semibold text-ink-950">
            {result.episodes_created} episode{result.episodes_created === 1 ? "" : "s"} imported as drafts
          </p>
          <p className="text-sm text-ink-500">
            They'll pick up AI styling automatically over the next little while, same as any episode you write by hand. Review and publish them whenever you're ready.
          </p>
          <div className="flex items-center justify-center gap-3">
            <Link
              to={`/admin/stories/${result.story.id}`}
              className="rounded-full bg-ink-950 px-5 py-2 text-sm font-medium text-parchment-50"
            >
              Go to story
            </Link>
            <button
              type="button"
              onClick={resetAll}
              className="rounded-full border border-ink-950/15 px-5 py-2 text-sm font-medium text-ink-700"
            >
              Import another
            </button>
          </div>
        </div>
      )}

      {preview.isPending && step === "upload" && <LoadingSpinner />}
    </div>
  );
}
