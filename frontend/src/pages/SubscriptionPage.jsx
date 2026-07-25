import { useEffect, useState } from "react";
import { useSubscriptionPlans, usePaymentGateways } from "../hooks/queries/useSubscriptions";
import { useSubscribeToPlan } from "../hooks/mutations/useInteractions";
import { useAuth } from "../context/AuthContext";
import PlanCard from "../components/subscription/PlanCard";
import LoadingSpinner from "../components/common/LoadingSpinner";
import Container from "../components/common/Container";

export default function SubscriptionPage() {
  const { isAuthenticated } = useAuth();
  const { data: plansData, isLoading } = useSubscriptionPlans();
  const { data: gatewaysData } = usePaymentGateways();
  const subscribe = useSubscribeToPlan();
  const [selected, setSelected] = useState(null);
  const [gateway, setGateway] = useState(null);

  const plans = plansData?.data ?? [];
  // Flutterwave-only single-provider phase (see GatewayAvailabilityService) - the
  // fallback matches that so nothing briefly flashes extra options while loading.
  const gateways = gatewaysData?.data ?? ["flutterwave"];

  // With just one active provider there's nothing to actually choose - picking
  // for the user and skipping the picker is less friction than a one-item list.
  useEffect(() => {
    if (gateways.length === 1) setGateway(gateways[0]);
  }, [gateways]);

  async function checkout() {
    if (!selected || !gateway) return;
    const res = await subscribe.mutateAsync({ planId: selected.id, gateway });
    if (res?.data?.checkout_url) window.location.href = res.data.checkout_url;
  }

  return (
    <Container className="py-6 pb-24">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Go premium</h1>
      <p className="mt-1 max-w-lg text-sm text-ink-500">
        Unlimited access to every episode of every story. Priced for where you live — pay in your local currency.
      </p>

      {isLoading ? (
        <LoadingSpinner />
      ) : (
        <div className="mt-6 grid gap-4 sm:grid-cols-3">
          {plans.map((p) => (
            <PlanCard key={p.id} plan={p} selected={selected?.id === p.id} onSelect={setSelected} />
          ))}
        </div>
      )}

      {selected && (
        <div className="mt-6 max-w-sm rounded-card border border-ink-950/10 bg-white/60 p-5">
          {gateways.length > 1 ? (
            <>
              <p className="text-sm font-medium text-ink-950">Pay with</p>
              <div className="mt-2 flex gap-2">
                {gateways.map((g) => (
                  <button
                    key={g}
                    onClick={() => setGateway(g)}
                    className={`rounded-full border px-3 py-1.5 text-xs font-medium capitalize ${
                      gateway === g ? "border-ink-950 bg-ink-950 text-parchment-50" : "border-ink-950/15 text-ink-700"
                    }`}
                  >
                    {g}
                  </button>
                ))}
              </div>
            </>
          ) : (
            <p className="text-sm text-ink-500">
              Paying via <span className="font-medium capitalize text-ink-950">{gateway}</span>
            </p>
          )}
          <button
            onClick={checkout}
            disabled={!isAuthenticated || !gateway || subscribe.isPending}
            className="mt-4 w-full rounded-card bg-gold-500 py-2.5 text-sm font-semibold text-ink-950 disabled:opacity-50"
          >
            {!isAuthenticated ? "Sign in first" : subscribe.isPending ? "Redirecting…" : `Subscribe to ${selected.name}`}
          </button>
        </div>
      )}
    </Container>
  );
}
