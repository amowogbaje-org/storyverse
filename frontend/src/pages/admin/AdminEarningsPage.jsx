import { useState } from "react";
import { useAdminEarnings } from "../../hooks/queries/useAdmin";
import StatCard from "../../components/admin/StatCard";
import LoadingSpinner from "../../components/common/LoadingSpinner";

export default function AdminEarningsPage() {
  const [days, setDays] = useState(30);
  const { data, isLoading } = useAdminEarnings(days, true);
  const e = data?.data;

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h2 className="font-display text-lg font-semibold text-ink-950">Earnings</h2>
        <select value={days} onChange={(ev) => setDays(Number(ev.target.value))}
          className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs">
          <option value={7}>Last 7 days</option>
          <option value={30}>Last 30 days</option>
          <option value={90}>Last 90 days</option>
        </select>
      </div>

      {isLoading || !e ? (
        <LoadingSpinner />
      ) : (
        <>
          <div className="rounded-card border border-gold-500/40 bg-gold-400/10 p-4 text-sm text-ink-700">
            Your share of story sales plus tips for this period, attributed exactly to your stories - not an
            estimate. Revenue is shown per currency since amounts aren't converted.
          </div>

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatCard label="Your share of sales" value={`${e.author_share_percent}%`} />
          </div>

          <div>
            <h3 className="mb-2 text-sm font-semibold text-ink-950">Gross story sales by currency</h3>
            {Object.keys(e.gross_story_sales_by_currency ?? {}).length ? (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {Object.entries(e.gross_story_sales_by_currency).map(([currency, amount]) => (
                  <StatCard key={currency} label={currency} value={amount} />
                ))}
              </div>
            ) : (
              <p className="text-sm text-ink-500">No story sales in this period yet.</p>
            )}
          </div>

          <div>
            <h3 className="mb-2 text-sm font-semibold text-ink-950">Tips by currency</h3>
            {Object.keys(e.tips_by_currency ?? {}).length ? (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {Object.entries(e.tips_by_currency).map(([currency, amount]) => (
                  <StatCard key={currency} label={currency} value={amount} />
                ))}
              </div>
            ) : (
              <p className="text-sm text-ink-500">No tips in this period yet.</p>
            )}
          </div>

          <div>
            <h3 className="mb-2 text-sm font-semibold text-ink-950">Your earnings by currency</h3>
            {Object.keys(e.your_earnings_by_currency ?? {}).length ? (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {Object.entries(e.your_earnings_by_currency).map(([currency, amount]) => (
                  <StatCard key={currency} label={currency} value={amount} />
                ))}
              </div>
            ) : (
              <p className="text-sm text-ink-500">Nothing earned in this period yet.</p>
            )}
          </div>
        </>
      )}
    </div>
  );
}
