import { useQuery } from "@tanstack/react-query";
import api from "../../api/client";

// Shared by story purchase checkout (BuyStoryButton) and tips (TipWidget) -
// "which payment providers work in this country", nothing subscription-
// specific despite the endpoint historically living under /payment-gateways.
export function usePaymentGateways() {
  return useQuery({
    queryKey: ["payment-gateways"],
    queryFn: async () => (await api.get("/payment-gateways")).data,
  });
}
