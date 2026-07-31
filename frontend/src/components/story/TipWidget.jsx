import { useState } from "react";
import { useAuth } from "../../context/AuthContext";
import { useNavigate } from "react-router-dom";
import { usePaymentGateways } from "../../hooks/queries/useSubscriptions";
import { useTipAuthor } from "../../hooks/mutations/useTipAuthor";

const PRESETS = {
  USD: [1, 5, 10],
  GBP: [1, 5, 10],
  NGN: [1000, 5000, 10000],
};

const SYMBOLS = { USD: "$", GBP: "£", NGN: "₦" };

export default function TipWidget({ penNameSlug, displayName }) {
  const { user, isAuthenticated } = useAuth();
  const navigate = useNavigate();
  const { data: gatewaysData } = usePaymentGateways();
  const tip = useTipAuthor(penNameSlug);

  const [open, setOpen] = useState(false);
  const [currency, setCurrency] = useState(user?.currency ?? "USD");
  const [amount, setAmount] = useState(PRESETS[user?.currency ?? "USD"][0]);
  const [customAmount, setCustomAmount] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState(null);

  const gateways = gatewaysData?.data ?? ["flutterwave"];
  const gateway = gateways[0]; // single-provider phase - see SubscriptionPage for the same pattern

  function pickCurrency(c) {
    setCurrency(c);
    setAmount(PRESETS[c][0]);
    setCustomAmount("");
  }

  async function send() {
    setError(null);
    const finalAmount = customAmount ? Number(customAmount) : amount;

    if (!finalAmount || finalAmount < 0.5) {
      setError("Enter an amount of at least 0.5.");
      return;
    }

    try {
      const { data } = await tip.mutateAsync({ amount: finalAmount, currency, gateway, message: message.trim() || undefined });
      if (data?.checkout_url) window.location.href = data.checkout_url;
    } catch (err) {
      setError(err.response?.data?.error?.message || "Couldn't start checkout. Please try again.");
    }
  }

  if (!open) {
    return (
      <button
        onClick={() => (isAuthenticated ? setOpen(true) : navigate(`/login?next=/authors/${penNameSlug}`))}
        className="rounded-full bg-gold-500 px-5 py-2 text-sm font-semibold text-ink-950"
      >
        💝 Support {displayName}
      </button>
    );
  }

  return (
    <div className="w-full max-w-sm rounded-card border border-ink-950/10 bg-white/70 p-4">
      <p className="text-sm font-medium text-ink-950">Send {displayName} a tip</p>
      <p className="mt-0.5 text-xs text-ink-500">A little encouragement goes a long way for an independent author.</p>

      <div className="mt-3 flex gap-1.5">
        {Object.keys(PRESETS).map((c) => (
          <button
            key={c}
            onClick={() => pickCurrency(c)}
            className={`rounded-full border px-3 py-1 text-xs font-medium ${
              currency === c ? "border-ink-950 bg-ink-950 text-parchment-50" : "border-ink-950/15 text-ink-700"
            }`}
          >
            {c}
          </button>
        ))}
      </div>

      <div className="mt-2 flex flex-wrap gap-1.5">
        {PRESETS[currency].map((p) => (
          <button
            key={p}
            onClick={() => { setAmount(p); setCustomAmount(""); }}
            className={`rounded-full border px-3 py-1.5 text-sm font-medium ${
              !customAmount && amount === p ? "border-gold-500 bg-gold-400/20 text-ink-950" : "border-ink-950/15 text-ink-700"
            }`}
          >
            {SYMBOLS[currency]}{p}
          </button>
        ))}
        <input
          type="number"
          min="0.5"
          step="0.5"
          value={customAmount}
          onChange={(e) => setCustomAmount(e.target.value)}
          placeholder="Custom"
          className="w-24 rounded-full border border-ink-950/15 bg-white px-3 py-1.5 text-sm"
        />
      </div>

      <textarea
        value={message}
        onChange={(e) => setMessage(e.target.value)}
        placeholder="Say something nice (optional)"
        rows={2}
        maxLength={500}
        className="mt-3 w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
      />

      {error && <p className="mt-2 text-xs text-ribbon-600">{error}</p>}

      <div className="mt-3 flex gap-2">
        <button
          onClick={send}
          disabled={tip.isPending}
          className="flex-1 rounded-card bg-gold-500 py-2 text-sm font-semibold text-ink-950 disabled:opacity-50"
        >
          {tip.isPending ? "Starting checkout…" : `Send ${SYMBOLS[currency]}${customAmount || amount}`}
        </button>
        <button onClick={() => setOpen(false)} className="rounded-card border border-ink-950/15 px-4 py-2 text-sm">
          Cancel
        </button>
      </div>
    </div>
  );
}
