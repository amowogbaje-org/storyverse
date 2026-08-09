import { useMutation, useQueryClient } from "@tanstack/react-query";
import api from "../../api/client";

export function useToggleLike(slug) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async () => (await api.post(`/stories/${slug}/like`)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["story", slug] });
    },
  });
}

export function useToggleBookmark(slug) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async () => (await api.post(`/stories/${slug}/bookmark`)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["story", slug] });
      qc.invalidateQueries({ queryKey: ["my-bookmarks"] });
    },
  });
}

export function useShareStory(slug) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ platform, episodeNumber }) =>
      (await api.post(`/stories/${slug}/share`, { platform, episode_number: episodeNumber })).data,
    onSuccess: (data) => {
      // Patch the count in place instead of a full refetch - keeps the click
      // feeling instant, which matters for something meant to be low-friction
      // enough that people actually bother sharing.
      qc.setQueryData(["story", slug], (old) =>
        old ? { ...old, data: { ...old.data, shares_count: data.data.shares_count } } : old
      );
    },
  });
}

export function usePostComment(slug) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (body) => (await api.post(`/stories/${slug}/comments`, { body })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["comments", slug] }),
  });
}

export function useRecordProgress(slug, episodeNumber) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (percent) =>
      (await api.post(`/stories/${slug}/episodes/${episodeNumber}/progress`, { percent })).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["story-progress", slug] });
      qc.invalidateQueries({ queryKey: ["story", slug] });
    },
  });
}
