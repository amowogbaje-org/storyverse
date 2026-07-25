import { Link, useParams } from "react-router-dom";
import { useStory } from "../hooks/queries/useStories";
import { useToggleLike, useToggleBookmark } from "../hooks/mutations/useInteractions";
import { useAuth } from "../context/AuthContext";
import StoryStats from "../components/story/StoryStats";
import ShareMenu from "../components/story/ShareMenu";
import EpisodeList from "../components/reader/EpisodeList";
import CommentList from "../components/comments/CommentList";
import CommentForm from "../components/comments/CommentForm";
import LoadingSpinner from "../components/common/LoadingSpinner";
import Container from "../components/common/Container";

export default function StoryDetailPage() {
  const { slug } = useParams();
  const { data, isLoading } = useStory(slug);
  const { isAuthenticated } = useAuth();
  const toggleLike = useToggleLike(slug);
  const toggleBookmark = useToggleBookmark(slug);

  if (isLoading) return <LoadingSpinner label="Loading story" />;
  const story = data?.data;
  if (!story) return null;

  return (
    <Container className="py-6">
      <div className="flex flex-col gap-6 sm:flex-row">
        <img
          src={story.cover_image_url}
          alt=""
          className="mx-auto h-64 w-44 shrink-0 rounded-card object-cover shadow-card sm:mx-0"
        />
        <div className="min-w-0 flex-1">
          {story.access_type !== "free" && (
            <span className="inline-block rounded-full bg-gold-500 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink-950">
              Premium
            </span>
          )}
          <h1 className="mt-1 font-display text-2xl font-semibold text-ink-950 sm:text-3xl">{story.title}</h1>
          <Link to={`/authors/${story.author_slug}`} className="text-sm text-teal-700 hover:underline">
            by {story.author_display_name}
          </Link>

          <div className="mt-2 flex flex-wrap gap-1.5">
            {story.category_name && (
              <span className="rounded-full bg-ink-950/8 px-2.5 py-0.5 text-xs text-ink-700">{story.category_name}</span>
            )}
            {(story.genres ?? []).map((g) => (
              <span key={g.slug} className="rounded-full bg-ink-950/8 px-2.5 py-0.5 text-xs text-ink-700">{g.name}</span>
            ))}
          </div>

          <p className="mt-3 text-sm text-ink-700">{story.description}</p>

          <StoryStats story={story} className="mt-4" />

          <div className="mt-4 flex gap-2">
            <button
              onClick={() => isAuthenticated && toggleLike.mutate()}
              disabled={!isAuthenticated}
              className={`rounded-full border px-4 py-1.5 text-sm font-medium ${
                story.is_liked_by_user ? "border-ribbon-500 bg-ribbon-500/10 text-ribbon-600" : "border-ink-950/15 text-ink-700"
              } disabled:opacity-40`}
              title={isAuthenticated ? "" : "Sign in to like stories"}
            >
              ♥ {story.is_liked_by_user ? "Liked" : "Like"}
            </button>
            <button
              onClick={() => isAuthenticated && toggleBookmark.mutate()}
              disabled={!isAuthenticated}
              className={`rounded-full border px-4 py-1.5 text-sm font-medium ${
                story.is_bookmarked_by_user ? "border-gold-500 bg-gold-400/10 text-gold-600" : "border-ink-950/15 text-ink-700"
              } disabled:opacity-40`}
              title={isAuthenticated ? "" : "Sign in to bookmark stories"}
            >
              ⚑ {story.is_bookmarked_by_user ? "Bookmarked" : "Bookmark"}
            </button>
            <ShareMenu slug={slug} title={story.title} sharesCount={story.shares_count} />
          </div>
        </div>
      </div>

      <section className="mt-8">
        <h2 className="mb-3 font-display text-lg font-semibold text-ink-950">Episodes</h2>
        <EpisodeList story={story} episodes={story.episodes ?? []} />
      </section>

      <section className="mt-8 max-w-2xl">
        <h2 className="mb-3 font-display text-lg font-semibold text-ink-950">Comments</h2>
        <CommentForm slug={slug} />
        <div className="mt-4">
          <CommentList slug={slug} />
        </div>
      </section>
    </Container>
  );
}
