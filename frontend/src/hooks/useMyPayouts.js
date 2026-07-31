import { useQuery } from "@tanstack/react-query";
import api from "../api/client";

export function useMyPayouts(enabled) {
  return useQuery({
    queryKey: ["my-payouts"],
    queryFn: async () => (await api.get("/me/payouts")).data,
    enabled,
  });
}
