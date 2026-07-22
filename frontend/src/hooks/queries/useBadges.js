import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function useAllBadges() {
  return useQuery({
    queryKey: ["badges"],
    queryFn: async () => (await api.get("/badges")).data,
    staleTime: 5 * 60_000,
  });
}

export function useMyBadges(enabled) {
  return useQuery({
    queryKey: ["my-badges"],
    queryFn: async () => (await api.get("/me/badges")).data,
    enabled,
  });
}
