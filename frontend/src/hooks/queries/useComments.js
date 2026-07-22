import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function useComments(slug) {
  return useQuery({
    queryKey: ["comments", slug],
    queryFn: async () => (await api.get(`/stories/${slug}/comments`)).data,
    enabled: !!slug,
  });
}
