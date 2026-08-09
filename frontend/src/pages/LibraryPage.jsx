import { useMyBookmarks } from "../hooks/queries/useLibrary";
import { useAuth } from "../context/AuthContext";
import { useLoginUrl } from "../hooks/useLoginUrl";
import StoryCard from "../components/story/StoryCard";
import LoadingSpinner from "../components/common/LoadingSpinner";
import EmptyState from "../components/common/EmptyState";
import Container from "../components/common/Container";
import { Link } from "react-router-dom";

export default function LibraryPage() {
  const { isAuthenticated } = useAuth();
  const loginHref = useLoginUrl();
  const { data, isLoading } = useMyBookmarks(isAuthenticated);
  const stories = data?.data ?? [];

  if (!isAuthenticated) {
    return (
      <Container className="py-10">
        <EmptyState
          title="Sign in to see your library"
          hint="Bookmarked stories and reading progress live here."
          action={<Link to={loginHref} className="rounded-full bg-ink-950 px-4 py-2 text-sm text-parchment-50">Sign in</Link>}
        />
      </Container>
    );
  }

  return (
    <Container className="py-6 pb-24">
      <h1 className="mb-4 font-display text-2xl font-semibold text-ink-950">My library</h1>
      {isLoading ? (
        <LoadingSpinner />
      ) : stories.length ? (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
          {stories.map((s) => <StoryCard key={s.id} story={s} />)}
        </div>
      ) : (
        <EmptyState title="No bookmarks yet" hint="Bookmark stories you want to come back to." />
      )}
    </Container>
  );
}
