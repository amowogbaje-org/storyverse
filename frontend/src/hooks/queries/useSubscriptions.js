import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

export function useSubscriptionPlans() {
  return useQuery({
    queryKey: ["subscription-plans"],
    queryFn: async () => (await api.get("/subscription-plans")).data,
  });
}

export function usePaymentGateways() {
  return useQuery({
    queryKey: ["payment-gateways"],
    queryFn: async () => (await api.get("/payment-gateways")).data,
  });
}

export function useMySubscription(enabled) {
  return useQuery({
    queryKey: ["my-subscription"],
    queryFn: async () => (await api.get("/me/subscription")).data,
    enabled,
  });
}
