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
