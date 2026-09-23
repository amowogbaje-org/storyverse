import { useEffect, useState } from "react";
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from "recharts";
import {
  useAdminAnalyticsTimeseries,
  useAdminTopStories,
  useAdminRetention,
  useAdminStickiness,
  useAdminStories,
  useStoryDropoff,
} from "../../hooks/queries/useAdmin";
import LoadingSpinner from "../../components/common/LoadingSpinner";
import TelescopeExportCard from "../../components/admin/TelescopeExportCard";

const METRICS = [
  { value: "reads", label: "Reads" },
  { value: "completed_reads", label: "Completed reads" },
  { value: "new_users", label: "New users" },
  { value: "page_views", label: "Page views" },
  { value: "likes", label: "Likes" },
  { value: "comments", label: "Comments" },
];

// Green the closer a retention cell is to 100%, fading toward the card
// background as it drops toward 0 - lets a leaky cohort jump out visually
// without reading every number.
function retentionCellStyle(value) {
  if (value === null || value === undefined) return {};
  const alpha = Math.max(value / 100, 0.06);
  return { backgroundColor: `rgba(60, 134, 119, ${alpha})` };
}

export default function AdminAnalyticsPage() {
  const [metric, setMetric] = useState("reads");
  const [days, setDays] = useState(30);
  const { data, isLoading } = useAdminAnalyticsTimeseries(metric, days, true);
  const { data: topStoriesData } = useAdminTopStories(days, true);
  const { data: retentionData, isLoading: retentionLoading } = useAdminRetention(8, 5, true);
  const { data: stickinessData } = useAdminStickiness(true);
  const { data: storiesData } = useAdminStories(true);

  const [dropoffSlug, setDropoffSlug] = useState("");
  const stories = storiesData?.data ?? [];
  useEffect(() => {
    if (!dropoffSlug && stories.length) setDropoffSlug(stories[0].slug);
  }, [stories, dropoffSlug]);
  const { data: dropoffData, isLoading: dropoffLoading } = useStoryDropoff(dropoffSlug, Boolean(dropoffSlug));

  const series = data?.data?.series ?? [];
  const topStories = topStoriesData?.data ?? [];
  const cohorts = retentionData?.data ?? [];
  const stickiness = stickinessData?.data;
  const episodes = dropoffData?.data?.episodes ?? [];

  return (
    <div className="space-y-8">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="font-display text-lg font-semibold text-ink-950">Platform analytics</h2>
        <div className="flex gap-2">
          <select value={metric} onChange={(e) => setMetric(e.target.value)}
            className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs dark:bg-parchment-100/70">
            {METRICS.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
          </select>
          <select value={days} onChange={(e) => setDays(Number(e.target.value))}
            className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs dark:bg-parchment-100/70">
            <option value={7}>7 days</option>
            <option value={30}>30 days</option>
            <option value={90}>90 days</option>
          </select>
        </div>
      </div>

      <div className="rounded-card border border-ink-950/10 bg-white/60 p-4 dark:bg-parchment-100/60">
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
        <div className="overflow-hidden rounded-card border border-ink-950/10 bg-white/60 dark:bg-parchment-100/60">
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

      {/* Retention: how much of a signup cohort is still doing anything on
          the site, week over week. This is the metric raw totals hide. */}
      <div>
        <div className="mb-2 flex flex-wrap items-baseline justify-between gap-2">
          <h3 className="font-display text-lg font-semibold text-ink-950">Retention</h3>
          {stickiness && (
            <div className="flex gap-4 text-xs text-ink-500">
              <span><strong className="text-ink-950">{stickiness.dau_avg_30d}</strong> avg daily active</span>
              <span><strong className="text-ink-950">{stickiness.mau_30d}</strong> monthly active</span>
              <span>
                <strong className="text-ink-950">
                  {stickiness.stickiness_ratio !== null ? `${Math.round(stickiness.stickiness_ratio * 100)}%` : "—"}
                </strong> stickiness (DAU/MAU)
              </span>
            </div>
          )}
        </div>
        <p className="mb-2 text-xs text-ink-500">
          Of everyone who signed up in a given week, the % still active (a page view or reading progress) N weeks later.
        </p>
        <div className="overflow-x-auto rounded-card border border-ink-950/10 bg-white/60 dark:bg-parchment-100/60">
          {retentionLoading ? (
            <div className="p-4"><LoadingSpinner /></div>
          ) : (
            <table className="w-full text-sm">
              <thead className="bg-ink-950/5 text-left text-xs uppercase text-ink-500">
                <tr>
                  <th className="px-4 py-2">Cohort week</th>
                  <th className="px-4 py-2">Size</th>
                  {Array.from({ length: cohorts[0]?.weeks.length ?? 5 }).map((_, w) => (
                    <th key={w} className="px-4 py-2 text-center">Week {w}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-ink-950/8">
                {cohorts.map((c) => (
                  <tr key={c.cohort_start}>
                    <td className="px-4 py-2 text-ink-500">{c.cohort_start}</td>
                    <td className="px-4 py-2 stat-num">{c.cohort_size}</td>
                    {c.weeks.map((v, w) => (
                      <td key={w} className="px-4 py-2 text-center stat-num" style={retentionCellStyle(v)}>
                        {v === null ? "—" : `${v}%`}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {/* Per-story episode drop-off: which specific episode readers quit at,
          not just a single completion percentage for the whole story. */}
      <div>
        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
          <h3 className="font-display text-lg font-semibold text-ink-950">Where readers drop off</h3>
          <select
            value={dropoffSlug}
            onChange={(e) => setDropoffSlug(e.target.value)}
            className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs dark:bg-parchment-100/70"
          >
            {stories.map((s) => <option key={s.slug} value={s.slug}>{s.title}</option>)}
          </select>
        </div>
        <div className="overflow-hidden rounded-card border border-ink-950/10 bg-white/60 dark:bg-parchment-100/60">
          {dropoffLoading ? (
            <div className="p-4"><LoadingSpinner /></div>
          ) : (
            <table className="w-full text-sm">
              <thead className="bg-ink-950/5 text-left text-xs uppercase text-ink-500">
                <tr>
                  <th className="px-4 py-2">Episode</th>
                  <th className="px-4 py-2">Readers</th>
                  <th className="px-4 py-2">Avg. progress</th>
                  <th className="px-4 py-2">Completed</th>
                  <th className="px-4 py-2">Carried over</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-ink-950/8">
                {episodes.map((e) => (
                  <tr key={e.episode_id}>
                    <td className="px-4 py-2 font-medium text-ink-950">
                      Ep {e.episode_number} · {e.title}
                    </td>
                    <td className="px-4 py-2 stat-num">{e.readers}</td>
                    <td className="px-4 py-2 stat-num">{e.avg_progress_percent}%</td>
                    <td className="px-4 py-2 stat-num">{e.completed}</td>
                    <td className="px-4 py-2 stat-num">
                      {e.carried_over_percent === null ? "—" : (
                        <span className={e.carried_over_percent < 60 ? "text-ribbon-600" : ""}>
                          {e.carried_over_percent}%
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
                {!episodes.length && (
                  <tr><td className="px-4 py-3 text-ink-500" colSpan={5}>No reading activity for this story yet.</td></tr>
                )}
              </tbody>
            </table>
          )}
        </div>
      </div>

      <TelescopeExportCard />
    </div>
  );
}
