import { useEffect, useState } from "react";
import { useAuth } from "../../context/AuthContext";
import api from "../../api/client";
import { useMyPayouts } from "../../hooks/useMyPayouts";

const STATUS_LABEL = {
  pending: "Pending",
  processing: "Processing",
  paid: "Paid",
  failed: "Failed",
};

export default function PayoutAccountSettings() {
  const { user, refresh } = useAuth();
  const { data: payoutsData } = useMyPayouts(true);
  const [accountName, setAccountName] = useState("");
  const [accountNumber, setAccountNumber] = useState("");
  const [bankName, setBankName] = useState("");
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!user) return;
    setAccountName(user.payout_account_name ?? "");
    setAccountNumber(user.payout_account_number ?? "");
    setBankName(user.payout_bank_name ?? "");
  }, [user]);

  const payouts = payoutsData?.data ?? [];

  async function save(e) {
    e.preventDefault();
    setError(null);
    setSaving(true);
    try {
      await api.patch("/me/payout-account", {
        payout_account_name: accountName,
        payout_account_number: accountNumber,
        payout_bank_name: bankName,
      });
      await refresh();
      setSaved(true);
      window.setTimeout(() => setSaved(false), 2000);
    } catch (err) {
      setError(err.response?.data?.error?.message || "Couldn't save your payout details.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="mt-6 rounded-card border border-ink-950/10 bg-white/40 p-4">
      <p className="text-sm font-medium text-ink-950">Payout account</p>
      <p className="mt-0.5 text-xs text-ink-500">
        Where your monthly earnings (story sales share + tips) get sent. Payouts are generated at the start of
        each month for the month before.
      </p>

      <form onSubmit={save} className="mt-3 space-y-2">
        <input
          value={accountName} onChange={(e) => setAccountName(e.target.value)} placeholder="Account holder name" required
          className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
        />
        <input
          value={accountNumber} onChange={(e) => setAccountNumber(e.target.value)} placeholder="Account number" required
          className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
        />
        <input
          value={bankName} onChange={(e) => setBankName(e.target.value)} placeholder="Bank name" required
          className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
        />
        {error && <p className="text-xs text-ribbon-600">{error}</p>}
        {saved && <p className="text-xs text-teal-700">Saved.</p>}
        <button
          type="submit" disabled={saving}
          className="rounded-card bg-ink-950 px-4 py-2 text-xs font-medium text-parchment-50 disabled:opacity-50"
        >
          {saving ? "Saving…" : "Save payout account"}
        </button>
      </form>

      {payouts.length > 0 && (
        <div className="mt-4 border-t border-ink-950/8 pt-3">
          <p className="mb-2 text-xs font-medium text-ink-500">Payout history</p>
          <ul className="space-y-1.5">
            {payouts.map((p) => (
              <li key={p.id} className="flex items-center justify-between text-xs">
                <span className="text-ink-700">
                  {new Date(p.period_start).toLocaleDateString(undefined, { month: "long", year: "numeric" })}
                </span>
                <span className="text-ink-900">{p.currency} {Number(p.total_amount).toFixed(2)}</span>
                <span className={p.status === "paid" ? "font-medium text-teal-700" : "text-ink-400"}>
                  {STATUS_LABEL[p.status] ?? p.status}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
