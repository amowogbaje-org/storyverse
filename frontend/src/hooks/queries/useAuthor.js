import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function useAuthor(slug) {
  return useQuery({
    queryKey: ["author", slug],
    queryFn: async () => (await api.get(`/authors/${slug}`)).data,
    enabled: !!slug,
  });
}

export function useAuthorStories(slug) {
  return useQuery({
    queryKey: ["author-stories", slug],
    queryFn: async () => (await api.get(`/authors/${slug}/stories`)).data,
    enabled: !!slug,
  });
}
