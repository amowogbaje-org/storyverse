import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function useNativeSearch(query) {
  return useQuery({
    queryKey: ["search", "native", query],
    queryFn: async () => (await api.get("/search", { params: { q: query } })).data,
    enabled: query.trim().length > 1,
  });
}
