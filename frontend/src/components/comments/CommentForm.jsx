import { useState } from "react";
import { useAuth } from "../../context/AuthContext";
import { usePostComment } from "../../hooks/mutations/useInteractions";

export default function CommentForm({ slug }) {
  const { isAuthenticated } = useAuth();
  const [body, setBody] = useState("");
  const postComment = usePostComment(slug);

  if (!isAuthenticated) {
    return <p className="text-sm text-ink-500">Sign in to join the conversation.</p>;
  }

  function submit(e) {
    e.preventDefault();
    if (!body.trim()) return;
    postComment.mutate(body.trim(), { onSuccess: () => setBody("") });
  }

  return (
    <form onSubmit={submit} className="flex gap-2">
      <input
        value={body}
        onChange={(e) => setBody(e.target.value)}
        placeholder="Share your thoughts…"
        className="flex-1 rounded-full border border-ink-950/15 bg-white/70 px-4 py-2 text-sm outline-none focus:border-gold-500"
      />
      <button
        type="submit"
        disabled={postComment.isPending}
        className="rounded-full bg-ink-950 px-4 py-2 text-sm font-medium text-parchment-50 disabled:opacity-50"
      >
        Post
      </button>
    </form>
  );
}
