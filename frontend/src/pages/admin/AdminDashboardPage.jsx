import { Link } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import { useAdminDashboard } from "../../hooks/queries/useAdmin";
import StatCard from "../../components/admin/StatCard";
import LoadingSpinner from "../../components/common/LoadingSpinner";
import EmptyState from "../../components/common/EmptyState";

export default function AdminDashboardPage() {
  const { user } = useAuth();
  const { data, isLoading } = useAdminDashboard(true);

  if (isLoading) return <LoadingSpinner label="Loading dashboard" />;
  const d = data?.data;
  if (!d) return null;

  if (user.role === "admin") {
    const t = d.totals;
    return (
      <div className="space-y-6">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <StatCard label="Readers" value={t.users} />
          <StatCard label="Published stories" value={t.published_stories} />
          <StatCard label="Total reads" value={t.reads} sub={`${t.completed_reads} completed`} />
          <StatCard label="Story purchases" value={t.story_purchases} />
          <StatCard label="Likes" value={t.likes} />
          <StatCard label="Bookmarks" value={t.bookmarks} />
          <StatCard label="Comments" value={t.comments} />
          <StatCard label="Page views" value={t.page_views} />
        </div>
        <Link to="/admin/analytics" className="inline-block rounded-full bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50">
          Open full analytics →
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <StatCard label="Your stories" value={d.stories_count} sub={`${d.published_stories_count} published`} />
        <StatCard label="Total reads" value={d.totals.reads} sub={`${d.totals.completed_reads} completed`} />
        <StatCard label="Likes" value={d.totals.likes} />
        <StatCard label="Bookmarks" value={d.totals.bookmarks} />
      </div>

      <div>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="font-display text-lg font-semibold text-ink-950">Your stories</h2>
          <Link to="/admin/stories" className="text-sm text-teal-700 hover:underline">Manage all →</Link>
        </div>
        {d.stories.length ? (
          <div className="overflow-hidden rounded-card border border-ink-950/10 bg-white/60">
            <table className="w-full text-sm">
              <thead className="bg-ink-950/5 text-left text-xs uppercase text-ink-500">
                <tr>
                  <th className="px-4 py-2">Title</th>
                  <th className="px-4 py-2">Status</th>
                  <th className="px-4 py-2">Views</th>
                  <th className="px-4 py-2">Likes</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-ink-950/8">
                {d.stories.map((s) => (
                  <tr key={s.id}>
                    <td className="px-4 py-2">
                      <Link to={`/admin/stories/${s.id}`} className="font-medium text-ink-950 hover:underline">{s.title}</Link>
                    </td>
                    <td className="px-4 py-2 capitalize text-ink-500">{s.status}</td>
                    <td className="px-4 py-2 stat-num">{s.views_count}</td>
                    <td className="px-4 py-2 stat-num">{s.likes_count}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState
            title="No stories yet"
            hint="Create your first pen name, then start your first story."
            action={<Link to="/admin/stories" className="rounded-full bg-ink-950 px-4 py-2 text-sm text-parchment-50">New story</Link>}
          />
        )}
      </div>
    </div>
  );
}
