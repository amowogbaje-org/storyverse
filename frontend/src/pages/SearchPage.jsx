import { useState } from "react";
import { useSearchParams } from "react-router-dom";
import { useNativeSearch } from "../hooks/queries/useSearch";
import StoryCard from "../components/story/StoryCard";
import LoadingSpinner from "../components/common/LoadingSpinner";
import EmptyState from "../components/common/EmptyState";
import Container from "../components/common/Container";
import api from "../api/client";

export default function SearchPage() {
  const [params, setParams] = useSearchParams();
  const [q, setQ] = useState(params.get("q") ?? "");
  const [mode, setMode] = useState("native"); // native keeps working even if AI search is off
  const [aiResult, setAiResult] = useState(null);
  const [aiLoading, setAiLoading] = useState(false);

  const { data, isLoading } = useNativeSearch(q);
  const stories = data?.data?.stories ?? [];
  const authors = data?.data?.authors ?? [];

  function submit(e) {
    e.preventDefault();
    setParams(q ? { q } : {});
    if (mode === "ai") runAiSearch();
  }

  async function runAiSearch() {
    if (!q.trim()) return;
    setAiLoading(true);
    try {
      const { data } = await api.post("/search/ai", { query: q });
      setAiResult(data.data);
    } catch {
      setAiResult(null);
    } finally {
      setAiLoading(false);
    }
  }

  return (
    <Container className="py-6">
      <form onSubmit={submit} className="flex gap-2">
        <input
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Search stories, authors, or describe what you want to read…"
          className="flex-1 rounded-full border border-ink-950/15 bg-white/70 px-4 py-2 text-sm outline-none focus:border-gold-500"
        />
        <button type="submit" className="rounded-full bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50">
          Search
        </button>
      </form>

      <div className="mt-3 inline-flex rounded-full border border-ink-950/15 bg-white/60 p-0.5 text-xs">
        <button
          onClick={() => setMode("native")}
          className={`rounded-full px-3 py-1 font-medium ${mode === "native" ? "bg-ink-950 text-parchment-50" : "text-ink-500"}`}
        >
          Search
        </button>
        <button
          onClick={() => { setMode("ai"); runAiSearch(); }}
          className={`rounded-full px-3 py-1 font-medium ${mode === "ai" ? "bg-ink-950 text-parchment-50" : "text-ink-500"}`}
        >
          Ask AI
        </button>
      </div>

      <div className="mt-6">
        {mode === "ai" ? (
          aiLoading ? (
            <LoadingSpinner label="Thinking" />
          ) : aiResult?.stories?.length ? (
            <div>
              {aiResult.source === "native_fallback" && (
                <p className="mb-3 text-xs text-ink-500">
                  AI search isn't available right now, showing regular search results instead.
                </p>
              )}
              <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                {aiResult.stories.map((s) => (
                  <div key={s.id}>
                    <StoryCard story={s} />
                    {s.ai_reason && <p className="mt-1.5 text-xs italic text-ink-500">"{s.ai_reason}"</p>}
                  </div>
                ))}
              </div>
            </div>
          ) : (
            <EmptyState title="Describe what you're in the mood for" hint="e.g. 'a slow-burn fantasy romance with a strong heroine'" />
          )
        ) : isLoading ? (
          <LoadingSpinner />
        ) : q.trim().length < 2 ? (
          <EmptyState title="Start typing to search" />
        ) : stories.length || authors.length ? (
          <div className="space-y-6">
            {authors.length > 0 && (
              <div>
                <h2 className="mb-2 text-sm font-semibold text-ink-950">Authors</h2>
                <div className="flex flex-wrap gap-2">
                  {authors.map((a) => (
                    <a key={a.slug} href={`/authors/${a.slug}`} className="rounded-full bg-white/70 px-3 py-1.5 text-sm border border-ink-950/10">
                      {a.display_name}
                    </a>
                  ))}
                </div>
              </div>
            )}
            {stories.length > 0 && (
              <div>
                <h2 className="mb-2 text-sm font-semibold text-ink-950">Stories</h2>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
                  {stories.map((s) => <StoryCard key={s.id} story={s} />)}
                </div>
              </div>
            )}
          </div>
        ) : (
          <EmptyState title="No results" hint="Try a different keyword, or switch to Ask AI." />
        )}
      </div>
    </Container>
  );
}
