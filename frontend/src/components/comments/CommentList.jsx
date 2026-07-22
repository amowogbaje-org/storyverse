import { useComments } from "../../hooks/queries/useComments";
import LoadingSpinner from "../common/LoadingSpinner";
import EmptyState from "../common/EmptyState";

export default function CommentList({ slug }) {
  const { data, isLoading } = useComments(slug);
  const comments = data?.data ?? [];

  if (isLoading) return <LoadingSpinner label="Loading comments" />;
  if (!comments.length) return <EmptyState title="No comments yet" hint="Be the first to say something." />;

  return (
    <ul className="space-y-4">
      {comments.map((c) => (
        <li key={c.id} className="flex gap-3">
          <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-teal-700 text-xs font-semibold text-parchment-50">
            {c.user_display_name?.[0]?.toUpperCase() ?? "U"}
          </span>
          <div>
            <p className="text-sm font-medium text-ink-950">{c.user_display_name}</p>
            <p className="text-sm text-ink-700">{c.body}</p>
          </div>
        </li>
      ))}
    </ul>
  );
}
