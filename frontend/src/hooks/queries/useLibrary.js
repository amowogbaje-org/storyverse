import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function useMyBookmarks(enabled) {
  return useQuery({
    queryKey: ["my-bookmarks"],
    queryFn: async () => (await api.get("/me/bookmarks")).data,
    enabled,
  });
}

export function useMyLibrary(enabled) {
  return useQuery({
    queryKey: ["my-library"],
    queryFn: async () => (await api.get("/me/library")).data,
    enabled,
  });
}
