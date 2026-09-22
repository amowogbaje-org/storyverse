import { useMutation, useQueryClient } from "@tanstack/react-query";
import api from "../../api/client";

export function useCreatePenName() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (payload) => (await api.post("/admin/pen-names", payload)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "pen-names"] }),
  });
}

export function useCreateStory() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (payload) => (await api.post("/admin/stories", payload)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "stories"] }),
  });
}

export function useUpdateStory(id) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (payload) => (await api.patch(`/admin/stories/${id}`, payload)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["admin", "story", id] });
      qc.invalidateQueries({ queryKey: ["admin", "stories"] });
    },
  });
}

export function useUploadCoverImage() {
  return useMutation({
    mutationFn: async (file) => {
      const form = new FormData();
      form.append("image", file);
      return (await api.post("/admin/uploads/cover-image", form)).data;
    },
  });
}

export function usePublishStory(id) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async () => (await api.post(`/admin/stories/${id}/publish`)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["admin", "story", id] });
      qc.invalidateQueries({ queryKey: ["admin", "stories"] });
    },
  });
}

export function useUnpublishStory(id) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async () => (await api.post(`/admin/stories/${id}/unpublish`)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["admin", "story", id] });
      qc.invalidateQueries({ queryKey: ["admin", "stories"] });
    },
  });
}

export function useCreateEpisode(storyId) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (payload) => (await api.post(`/admin/stories/${storyId}/episodes`, payload)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "story", storyId, "episodes"] }),
  });
}

export function useUpdateEpisode(storyId) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ episodeId, ...payload }) =>
      (await api.patch(`/admin/stories/${storyId}/episodes/${episodeId}`, payload)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "story", storyId, "episodes"] }),
  });
}

export function usePublishEpisode(storyId) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (episodeId) => (await api.post(`/admin/stories/${storyId}/episodes/${episodeId}/publish`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "story", storyId, "episodes"] }),
  });
}

export function usePreviewStoryImport() {
  return useMutation({
    mutationFn: async ({ file, pen_name_id }) => {
      const form = new FormData();
      form.append("file", file);
      form.append("pen_name_id", pen_name_id);
      return (await api.post("/admin/story-imports/preview", form)).data;
    },
  });
}

export function useConfirmStoryImport() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (payload) => (await api.post("/admin/story-imports", payload)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "stories"] }),
  });
}

/** Downloads the canonical bulk-import template - a blob fetch + object URL rather than a plain <a href>, since the endpoint requires the same bearer-token auth as everything else under /admin. */
export function useDownloadStoryImportTemplate() {
  return useMutation({
    mutationFn: async () => {
      const response = await api.get("/admin/story-imports/template", { responseType: "blob" });
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement("a");
      link.href = url;
      link.download = "storyverse-bulk-import-template.md";
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    },
  });
}

export function useAddToBlacklist() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ email, reason }) => (await api.post("/admin/emails/blacklist", { email, reason })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "email-blacklist"] }),
  });
}

export function useRemoveFromBlacklist() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (email) => (await api.delete(`/admin/emails/blacklist/${encodeURIComponent(email)}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "email-blacklist"] }),
  });
}

export function useMarkPayoutPaid() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id) => (await api.post(`/admin/payouts/${id}/mark-paid`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "payouts"] }),
  });
}

export function useSendPayout() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id) => (await api.post(`/admin/payouts/${id}/send`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "payouts"] }),
  });
}

export function useGrantAuthor() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id) => (await api.post(`/admin/users/${id}/grant-author`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "users"] }),
  });
}

export function useRejectAuthorRequest() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id) => (await api.post(`/admin/users/${id}/reject-author-request`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "users"] }),
  });
}

/** unpublishStories defaults true on the backend - pass false explicitly to keep the author's stories live after revoking. */
export function useRevokeAuthor() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, unpublishStories }) =>
      (await api.post(`/admin/users/${id}/revoke-author`, { unpublish_stories: unpublishStories })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin", "users"] }),
  });
}
