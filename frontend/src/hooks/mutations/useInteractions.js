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

export function useSubscribeToPlan() {
  return useMutation({
    mutationFn: async ({ planId, gateway }) =>
      (await api.post("/subscriptions/checkout", { plan_id: planId, gateway })).data,
  });
}
