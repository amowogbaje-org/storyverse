import { useState } from "react";
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from "recharts";
import { useAdminAnalyticsTimeseries, useAdminTopStories } from "../../hooks/queries/useAdmin";
import LoadingSpinner from "../../components/common/LoadingSpinner";

const METRICS = [
  { value: "reads", label: "Reads" },
  { value: "completed_reads", label: "Completed reads" },
  { value: "new_users", label: "New users" },
  { value: "page_views", label: "Page views" },
  { value: "likes", label: "Likes" },
  { value: "comments", label: "Comments" },
];

export default function AdminAnalyticsPage() {
  const [metric, setMetric] = useState("reads");
  const [days, setDays] = useState(30);
  const { data, isLoading } = useAdminAnalyticsTimeseries(metric, days, true);
  const { data: topStoriesData } = useAdminTopStories(days, true);

  const series = data?.data?.series ?? [];
  const topStories = topStoriesData?.data ?? [];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="font-display text-lg font-semibold text-ink-950">Platform analytics</h2>
        <div className="flex gap-2">
          <select value={metric} onChange={(e) => setMetric(e.target.value)}
            className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs">
            {METRICS.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
          </select>
          <select value={days} onChange={(e) => setDays(Number(e.target.value))}
            className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs">
            <option value={7}>7 days</option>
            <option value={30}>30 days</option>
            <option value={90}>90 days</option>
          </select>
        </div>
      </div>

      <div className="rounded-card border border-ink-950/10 bg-white/60 p-4">
        {isLoading ? (
          <LoadingSpinner />
        ) : (
          <ResponsiveContainer width="100%" height={280}>
            <LineChart data={series}>
              <CartesianGrid stroke="#14101F" strokeOpacity={0.08} />
              <XAxis dataKey="date" tick={{ fontSize: 11 }} tickFormatter={(d) => d.slice(5)} />
              <YAxis tick={{ fontSize: 11 }} allowDecimals={false} />
              <Tooltip />
              <Line type="monotone" dataKey="value" stroke="#C0983D" strokeWidth={2} dot={false} />
            </LineChart>
          </ResponsiveContainer>
        )}
      </div>

      <div>
        <h3 className="mb-2 font-display text-lg font-semibold text-ink-950">Top stories</h3>
        <div className="overflow-hidden rounded-card border border-ink-950/10 bg-white/60">
          <table className="w-full text-sm">
            <thead className="bg-ink-950/5 text-left text-xs uppercase text-ink-500">
              <tr>
                <th className="px-4 py-2">Title</th><th className="px-4 py-2">Author</th>
                <th className="px-4 py-2">Views</th><th className="px-4 py-2">Recent likes</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-ink-950/8">
              {topStories.map((s) => (
                <tr key={s.slug}>
                  <td className="px-4 py-2 font-medium text-ink-950">{s.title}</td>
                  <td className="px-4 py-2 text-ink-500">{s.author_display_name}</td>
                  <td className="px-4 py-2 stat-num">{s.views_count}</td>
                  <td className="px-4 py-2 stat-num">{s.recent_likes}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
