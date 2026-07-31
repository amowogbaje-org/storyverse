import { useMutation } from "@tanstack/react-query";
import api from "../../api/client";

export function useTipAuthor(slug) {
  return useMutation({
    mutationFn: async ({ amount, currency, gateway, message }) =>
      (await api.post(`/authors/${slug}/tip`, { amount, currency, gateway, message })).data,
  });
}
