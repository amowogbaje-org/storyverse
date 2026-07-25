import { useEffect, useRef, useState } from "react";
import { useParams } from "react-router-dom";
import { useAdminStory, useAdminEpisodes } from "../../hooks/queries/useAdmin";
import {
  useUpdateStory, usePublishStory, useUnpublishStory,
  useCreateEpisode, useUpdateEpisode, usePublishEpisode,
  useUploadCoverImage,
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
  const updateEpisode = useUpdateEpisode(id);
  const publishEpisode = usePublishEpisode(id);
  const uploadCover = useUploadCoverImage();

  const story = storyData?.data;
  const episodes = episodesData?.data ?? [];

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [coverImageUrl, setCoverImageUrl] = useState("");
  const [coverMode, setCoverMode] = useState("url"); // "url" | "upload"
  const [coverError, setCoverError] = useState(null);
  const fileInputRef = useRef(null);

  const [newEpisodeTitle, setNewEpisodeTitle] = useState("");
  const [newEpisodeContent, setNewEpisodeContent] = useState("");
  const [editingEpisodeId, setEditingEpisodeId] = useState(null);
  const [editTitle, setEditTitle] = useState("");
  const [editContent, setEditContent] = useState("");

  useEffect(() => {
    if (story) {
      setTitle(story.title);
      setDescription(story.description);
      setCoverImageUrl(story.cover_image_url ?? "");
    }
  }, [story?.id]);

  if (isLoading) return <LoadingSpinner label="Loading story" />;
  if (!story) return null;

  function saveDetails(e) {
    e.preventDefault();
    updateStory.mutate({ title, description, cover_image_url: coverImageUrl });
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
    setEditContent(ep.content ?? "");
  }

  function saveEpisodeEdit(e, episodeId) {
    e.preventDefault();
    updateEpisode.mutate(
      { episodeId, title: editTitle.trim(), content: editContent.trim() },
      { onSuccess: () => setEditingEpisodeId(null) }
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
              <img src={coverImageUrl} alt="Cover preview" className="h-28 w-20 shrink-0 rounded-card border border-ink-950/10 object-cover" />
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

      <div>
        <h3 className="mb-3 font-display text-lg font-semibold text-ink-950">Episodes</h3>
        <ul className="mb-4 divide-y divide-ink-950/8 rounded-card border border-ink-950/10 bg-white/60">
          {episodes.map((ep) => (
            <li key={ep.id} className="px-4 py-3">
              {editingEpisodeId === ep.id ? (
                <form onSubmit={(e) => saveEpisodeEdit(e, ep.id)} className="space-y-2">
                  <input value={editTitle} onChange={(e) => setEditTitle(e.target.value)}
                    className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
                  <textarea value={editContent} onChange={(e) => setEditContent(e.target.value)} rows={6}
                    className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm" />
                  <div className="flex gap-2">
                    <button type="submit" disabled={updateEpisode.isPending}
                      className="rounded-full bg-ink-950 px-4 py-1.5 text-xs font-medium text-parchment-50 disabled:opacity-50">
                      {updateEpisode.isPending ? "Saving…" : "Save changes"}
                    </button>
                    <button type="button" onClick={() => setEditingEpisodeId(null)}
                      className="rounded-full border border-ink-950/15 px-4 py-1.5 text-xs">
                      Cancel
                    </button>
                  </div>
                  {updateEpisode.isError && (
                    <p className="text-xs text-ribbon-600">
                      {updateEpisode.error?.response?.data?.error?.message ?? "Couldn't save this episode."}
                    </p>
                  )}
                </form>
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
