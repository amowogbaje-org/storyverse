import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function useNotifications(enabled) {
  return useQuery({
    queryKey: ["notifications"],
    queryFn: async () => (await api.get("/me/notifications")).data,
    enabled,
  });
}

export function useUnreadNotificationCount(enabled) {
  return useQuery({
    queryKey: ["notifications", "unread-count"],
    queryFn: async () => (await api.get("/me/notifications/unread-count")).data,
    enabled,
    // Polling rather than websockets - simple, and notifications aren't
    // urgent enough (unlike, say, a chat) to justify a persistent connection.
    refetchInterval: 60_000,
  });
}
