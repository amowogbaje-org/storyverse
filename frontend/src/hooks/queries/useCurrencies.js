import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

// Long cache/staleTime: this list changes only when the backend adds a
// currency (a deploy, not a runtime event), so there's no reason to refetch
// it more than once per session.
export function useCurrencies() {
  return useQuery({
    queryKey: ["currencies"],
    queryFn: async () => (await api.get("/currencies")).data,
    staleTime: 60 * 60 * 1000,
  });
}
