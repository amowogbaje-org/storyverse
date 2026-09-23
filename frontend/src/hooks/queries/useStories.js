import { useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect } from "react";
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

/**
 * One request for everything the homepage needs (new releases, popular,
 * genres, and - if logged in - the next-badge teaser), replacing the 4
 * separate calls useNewReleases/usePopularStories/useGenres/useNextBadges
 * used to fire on mount. The individual hooks are left in place (other
 * pages - FilterBar, admin, the badges page - still use them directly),
 * but their query caches are seeded here, so if the homepage has already
 * loaded, navigating to a page that calls e.g. useGenres() reuses this
 * data instead of firing a fresh request (within the default 30s staleTime).
 */
export function useHomeData(isAuthenticated) {
  const queryClient = useQueryClient();
  const badgesLimit = 1; // matches the homepage's compact <NextBadges limit={1} />

  const query = useQuery({
    queryKey: ["home", isAuthenticated],
    queryFn: async () => (await api.get("/home", { params: { badges_limit: badgesLimit } })).data,
  });

  useEffect(() => {
    if (!query.data) return;
    const { new_releases, popular, genres, next_badges } = query.data.data;
    queryClient.setQueryData(["stories", "new-releases"], { data: new_releases });
    queryClient.setQueryData(["stories", "popular"], { data: popular });
    queryClient.setQueryData(["genres"], { data: genres });
    if (isAuthenticated) {
      queryClient.setQueryData(["next-badges", badgesLimit], { data: next_badges });
    }
  }, [query.data, isAuthenticated, queryClient]);

  return {
    newReleases: query.data?.data?.new_releases ?? [],
    popular: query.data?.data?.popular ?? [],
    genres: query.data?.data?.genres ?? [],
    isLoading: query.isLoading,
  };
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
