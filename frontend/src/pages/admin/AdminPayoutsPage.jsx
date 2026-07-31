import { useState } from "react";
import { useAuth } from "../../context/AuthContext";
import { useAdminPayouts } from "../../hooks/queries/useAdmin";
import { useMarkPayoutPaid, useSendPayout } from "../../hooks/mutations/useAdminMutations";
import LoadingSpinner from "../../components/common/LoadingSpinner";

const STATUSES = ["", "pending", "processing", "paid", "failed"];

export default function AdminPayoutsPage() {
  const { user } = useAuth();
  const [status, setStatus] = useState("");
  const { data, isLoading } = useAdminPayouts(status, user?.role === "admin");
  const markPaid = useMarkPayoutPaid();
  const send = useSendPayout();

  if (user?.role !== "admin") {
    return <p className="text-sm text-ink-500">This page is only available to platform admins.</p>;
  }

  const payouts = data?.data ?? [];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="font-display text-lg font-semibold text-ink-950">Payouts</h2>
        <div className="flex gap-1.5">
          {STATUSES.map((s) => (
            <button
              key={s || "all"}
              onClick={() => setStatus(s)}
              className={`rounded-full border px-3 py-1 text-xs font-medium capitalize ${
                status === s ? "border-ink-950 bg-ink-950 text-parchment-50" : "border-ink-950/15 text-ink-700"
              }`}
            >
              {s || "All"}
            </button>
          ))}
        </div>
      </div>

      <p className="text-xs text-ink-500">
        Generated automatically at the start of each month for the month before. "Mark paid" is for payouts sent
        manually (bank transfer done outside the system); "Send" attempts an automated transfer via the payment
        gateway, if one's configured for it.
      </p>

      {isLoading ? (
        <LoadingSpinner />
      ) : (
        <div className="overflow-hidden rounded-card border border-ink-950/10 bg-white/60">
          <table className="w-full text-sm">
            <thead className="bg-ink-950/5 text-left text-xs uppercase text-ink-500">
              <tr>
                <th className="px-4 py-2">Author</th>
                <th className="px-4 py-2">Period</th>
                <th className="px-4 py-2">Amount</th>
                <th className="px-4 py-2">Account</th>
                <th className="px-4 py-2">Status</th>
                <th className="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-ink-950/8">
              {payouts.map((p) => (
                <tr key={p.id}>
                  <td className="px-4 py-2 font-medium text-ink-950">{p.author}</td>
                  <td className="px-4 py-2 text-ink-500">
                    {new Date(p.period_start).toLocaleDateString(undefined, { month: "short", year: "numeric" })}
                  </td>
                  <td className="px-4 py-2 text-ink-900">{p.currency} {Number(p.total_amount).toFixed(2)}</td>
                  <td className="px-4 py-2 text-xs text-ink-500">
                    {p.payout_bank_name ? `${p.payout_bank_name} · ${p.payout_account_number}` : "Not on file"}
                  </td>
                  <td className="px-4 py-2">
                    <span className={`text-xs font-medium capitalize ${p.status === "paid" ? "text-teal-700" : p.status === "failed" ? "text-ribbon-600" : "text-ink-500"}`}>
                      {p.status}
                    </span>
                    {p.failure_reason && <p className="mt-0.5 max-w-[200px] text-[10px] text-ribbon-500">{p.failure_reason}</p>}
                  </td>
                  <td className="px-4 py-2 text-right">
                    {p.status !== "paid" && (
                      <div className="flex justify-end gap-1.5">
                        <button
                          onClick={() => send.mutate(p.id)}
                          disabled={send.isPending || !p.payout_account_number}
                          className="rounded-full border border-ink-950/15 px-3 py-1 text-xs disabled:opacity-40"
                        >
                          Send
                        </button>
                        <button
                          onClick={() => markPaid.mutate(p.id)}
                          disabled={markPaid.isPending}
                          className="rounded-full bg-ink-950 px-3 py-1 text-xs text-parchment-50 disabled:opacity-50"
                        >
                          Mark paid
                        </button>
                      </div>
                    )}
                  </td>
                </tr>
              ))}
              {!payouts.length && (
                <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-ink-500">No payouts in this filter.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
