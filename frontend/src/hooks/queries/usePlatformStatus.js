import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function usePlatformStatus() {
  return useQuery({
    queryKey: ["platform-status"],
    queryFn: async () => (await api.get("/platform-status")).data.data,
    staleTime: 5 * 60 * 1000, // these barely change; no need to refetch aggressively
  });
}

// Used only for the brief moment before the query above resolves - matches
// the backend's own config/access.php defaults, so there's no visible flash
// of "everything unlocked" while loading.
const DEFAULT_ACCESS_LIMITS = { guestEpisodeLimit: 2, registeredPremiumEpisodeLimit: 14 };

/** @returns {{guestEpisodeLimit: number, registeredPremiumEpisodeLimit: number}} */
export function useAccessLimits() {
  const { data } = usePlatformStatus();
  if (!data) return DEFAULT_ACCESS_LIMITS;
  return {
    guestEpisodeLimit: data.guest_episode_limit,
    registeredPremiumEpisodeLimit: data.registered_premium_episode_limit,
  };
}
