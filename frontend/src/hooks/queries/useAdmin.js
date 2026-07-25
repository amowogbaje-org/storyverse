import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function useAdminDashboard(enabled) {
  return useQuery({
    queryKey: ["admin", "dashboard"],
    queryFn: async () => (await api.get("/admin/dashboard")).data,
    enabled,
  });
}

export function useAdminEarnings(days = 30, enabled) {
  return useQuery({
    queryKey: ["admin", "earnings", days],
    queryFn: async () => (await api.get("/admin/earnings", { params: { days } })).data,
    enabled,
  });
}

export function useAdminPenNames(enabled) {
  return useQuery({
    queryKey: ["admin", "pen-names"],
    queryFn: async () => (await api.get("/admin/pen-names")).data,
    enabled,
  });
}

export function useAdminStories(enabled) {
  return useQuery({
    queryKey: ["admin", "stories"],
    queryFn: async () => (await api.get("/admin/stories")).data,
    enabled,
  });
}

export function useAdminStory(id, enabled) {
  return useQuery({
    queryKey: ["admin", "story", id],
    queryFn: async () => (await api.get(`/admin/stories/${id}`)).data,
    enabled: enabled && !!id,
  });
}

export function useAdminEpisodes(storyId, enabled) {
  return useQuery({
    queryKey: ["admin", "story", storyId, "episodes"],
    queryFn: async () => (await api.get(`/admin/stories/${storyId}/episodes`)).data,
    enabled: enabled && !!storyId,
  });
}

// Platform-wide — admin role only (401/403 handled by the caller, not gated here)
export function useAdminAnalyticsOverview(enabled) {
  return useQuery({
    queryKey: ["admin", "analytics", "overview"],
    queryFn: async () => (await api.get("/admin/analytics/overview")).data,
    enabled,
  });
}

export function useAdminAnalyticsTimeseries(metric, days = 30, enabled) {
  return useQuery({
    queryKey: ["admin", "analytics", "timeseries", metric, days],
    queryFn: async () => (await api.get("/admin/analytics/timeseries", { params: { metric, days } })).data,
    enabled,
  });
}

export function useAdminTopStories(days = 30, enabled) {
  return useQuery({
    queryKey: ["admin", "analytics", "top-stories", days],
    queryFn: async () => (await api.get("/admin/analytics/top-stories", { params: { days } })).data,
    enabled,
  });
}

export function useAdminEmailBlacklist(enabled) {
  return useQuery({
    queryKey: ["admin", "email-blacklist"],
    queryFn: async () => (await api.get("/admin/emails/blacklist")).data,
    enabled,
  });
}
