import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function useMyReferrals(enabled) {
  return useQuery({
    queryKey: ["my-referrals"],
    queryFn: async () => (await api.get("/me/referrals")).data,
    enabled,
  });
}
