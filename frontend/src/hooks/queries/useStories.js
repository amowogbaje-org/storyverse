import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function useStories(params = {}) {
  return useQuery({
    queryKey: ["stories", params],
    queryFn: async () => {
      const { data } = await api.get("/stories", { params });
      return data;
    },
  });
}

export function useNewReleases() {
  return useQuery({
    queryKey: ["stories", "new-releases"],
    queryFn: async () => (await api.get("/stories/new-releases")).data,
  });
}

export function usePopularStories() {
  return useQuery({
    queryKey: ["stories", "popular"],
    queryFn: async () => (await api.get("/stories/popular")).data,
  });
}

export function useStory(slug) {
  return useQuery({
    queryKey: ["story", slug],
    queryFn: async () => (await api.get(`/stories/${slug}`)).data,
    enabled: !!slug,
  });
}

export function useEpisode(slug, episodeNumber) {
  return useQuery({
    queryKey: ["episode", slug, episodeNumber],
    queryFn: async () => (await api.get(`/stories/${slug}/episodes/${episodeNumber}`)).data,
    enabled: !!slug && !!episodeNumber,
  });
}

export function useCategories() {
  return useQuery({
    queryKey: ["categories"],
    queryFn: async () => (await api.get("/categories")).data,
    staleTime: 5 * 60_000,
  });
}

export function useGenres() {
  return useQuery({
    queryKey: ["genres"],
    queryFn: async () => (await api.get("/genres")).data,
    staleTime: 5 * 60_000,
  });
}

export function useStoryProgress(slug, enabled) {
  return useQuery({
    queryKey: ["story-progress", slug],
    queryFn: async () => (await api.get(`/stories/${slug}/progress`)).data,
    enabled: !!slug && !!enabled,
  });
}
