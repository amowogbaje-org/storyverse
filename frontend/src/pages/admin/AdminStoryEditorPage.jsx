import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { useAdminStory, useAdminEpisodes } from "../../hooks/queries/useAdmin";
import {
  useUpdateStory, usePublishStory, useUnpublishStory,
  useCreateEpisode, usePublishEpisode,
} from "../../hooks/mutations/useAdminMutations";
import LoadingSpinner from "../../components/common/LoadingSpinner";

export default function AdminStoryEditorPage() {
  const { id } = useParams();
  const { data: storyData, isLoading } = useAdminStory(id, true);
  const { data: episodesData } = useAdminEpisodes(id, true);
  const updateStory = useUpdateStory(id);
  const publishStory = usePublishStory(id);
  const unpublishStory = useUnpublishStory(id);
  const createEpisode = useCreateEpisode(id);
  const publishEpisode = usePublishEpisode(id);

  const story = storyData?.data;
  const episodes = episodesData?.data ?? [];

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [newEpisodeTitle, setNewEpisodeTitle] = useState("");
  const [newEpisodeContent, setNewEpisodeContent] = useState("");

  useEffect(() => {
    if (story) {
      setTitle(story.title);
      setDescription(story.description);
    }
  }, [story?.id]);

  if (isLoading) return <LoadingSpinner label="Loading story" />;
  if (!story) return null;

  function saveDetails(e) {
    e.preventDefault();
    updateStory.mutate({ title, description });
  }

  function addEpisode(e) {
    e.preventDefault();
    if (!newEpisodeTitle.trim() || !newEpisodeContent.trim()) return;
    createEpisode.mutate(
      { title: newEpisodeTitle.trim(), content: newEpisodeContent.trim() },
      { onSuccess: () => { setNewEpisodeTitle(""); setNewEpisodeContent(""); } }
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

      <form onSubmit={saveDetails} className="space-y-3 rounded-card border border-ink-950/10 bg-white/60 p-4">
        <input value={title} onChange={(e) => setTitle(e.target.value)}
          className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
        <textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3}
          className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
        <button type="submit" disabled={updateStory.isPending}
          className="rounded-card bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50 disabled:opacity-50">
          {updateStory.isPending ? "Saving…" : "Save details"}
        </button>
      </form>

      <div>
        <h3 className="mb-3 font-display text-lg font-semibold text-ink-950">Episodes</h3>
        <ul className="mb-4 divide-y divide-ink-950/8 rounded-card border border-ink-950/10 bg-white/60">
          {episodes.map((ep) => (
            <li key={ep.id} className="flex items-center justify-between gap-3 px-4 py-3">
              <div className="min-w-0">
                <p className="truncate text-sm font-medium text-ink-950">Ep {ep.episode_number} · {ep.title}</p>
                <p className="text-xs text-ink-500">{ep.word_count} words · {ep.status}</p>
              </div>
              {ep.status !== "published" && (
                <button
                  onClick={() => publishEpisode.mutate(ep.id)}
                  className="shrink-0 rounded-full border border-gold-500 px-3 py-1 text-xs font-medium text-gold-600"
                >
                  Publish
                </button>
              )}
            </li>
          ))}
          {!episodes.length && <li className="px-4 py-6 text-center text-sm text-ink-500">No episodes yet.</li>}
        </ul>

        <form onSubmit={addEpisode} className="space-y-3 rounded-card border border-ink-950/10 bg-white/60 p-4">
          <p className="text-sm font-medium text-ink-950">Add episode</p>
          <input value={newEpisodeTitle} onChange={(e) => setNewEpisodeTitle(e.target.value)} placeholder="Episode title"
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
          <textarea value={newEpisodeContent} onChange={(e) => setNewEpisodeContent(e.target.value)} placeholder="Episode content" rows={6}
            className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
          <button type="submit" disabled={createEpisode.isPending}
            className="rounded-card bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50 disabled:opacity-50">
            {createEpisode.isPending ? "Adding…" : "Add as draft"}
          </button>
        </form>
      </div>
    </div>
  );
}
