import { useQuery, useMutation } from "@tanstack/react-query";
import api from "../../api/client";

export function usePurchaseStatus(slug, enabled) {
  return useQuery({
    queryKey: ["purchase-status", slug],
    queryFn: async () => (await api.get(`/stories/${slug}/purchase-status`)).data,
    enabled: !!slug && !!enabled,
  });
}

export function useBuyStory(slug) {
  return useMutation({
    mutationFn: async ({ gateway }) => (await api.post(`/stories/${slug}/purchase`, { gateway })).data,
  });
}
