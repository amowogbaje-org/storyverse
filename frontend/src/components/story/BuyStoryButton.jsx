import { useEffect, useState } from "react";
import { useAuth } from "../../context/AuthContext";
import { usePaymentGateways } from "../../hooks/queries/useSubscriptions";
import { usePurchaseStatus, useBuyStory } from "../../hooks/mutations/useBuyStory";

const CURRENCY_SYMBOLS = { USD: "$", GBP: "£", NGN: "₦" };

/**
 * Only renders anything once we know the story actually has a purchase price
 * set (purchase-status tells us that) - most stories won't, so this quietly
 * renders nothing rather than showing a disabled/irrelevant button.
 */
export default function BuyStoryButton({ slug }) {
  const { isAuthenticated } = useAuth();
  const { data: statusData, isLoading } = usePurchaseStatus(slug, isAuthenticated);
  const { data: gatewaysData } = usePaymentGateways();
  const buy = useBuyStory(slug);
  const [gateway, setGateway] = useState(null);
  const [error, setError] = useState(null);

  const gateways = gatewaysData?.data ?? ["flutterwave"];

  useEffect(() => {
    if (gateways.length >= 1 && !gateway) setGateway(gateways[0]);
  }, [gateways, gateway]);

  if (!isAuthenticated || isLoading) return null;

  const status = statusData?.data;
  if (!status?.purchasable) return null;

  if (status.purchased) {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full border border-teal-600/30 bg-teal-600/10 px-4 py-1.5 text-sm font-medium text-teal-700">
        ✓ You own this book
      </span>
    );
  }

  const symbol = CURRENCY_SYMBOLS[status.currency] ?? `${status.currency} `;

  async function handleBuy() {
    setError(null);
    try {
      const { data } = await buy.mutateAsync({ gateway });
      window.location.href = data.checkout_url;
    } catch (err) {
      setError(err.response?.data?.error?.message || "Couldn't start checkout. Please try again.");
    }
  }

  return (
    <div className="inline-flex flex-col gap-1">
      <button
        type="button"
        onClick={handleBuy}
        disabled={buy.isPending || !gateway}
        className="rounded-full bg-gold-500 px-4 py-1.5 text-sm font-semibold text-ink-950 disabled:opacity-50"
      >
        {buy.isPending ? "Starting checkout…" : `Buy this book — ${symbol}${status.price}`}
      </button>
      {error && <p className="text-xs text-ribbon-600">{error}</p>}
    </div>
  );
}
