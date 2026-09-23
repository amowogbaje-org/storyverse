import { useParams } from "react-router-dom";
import { useAuthor } from "../hooks/queries/useAuthor";
import StoryCard from "../components/story/StoryCard";
import TipWidget from "../components/story/TipWidget";
import LoadingSpinner from "../components/common/LoadingSpinner";
import EmptyState from "../components/common/EmptyState";
import Container from "../components/common/Container";

export default function AuthorPage() {
  const { slug } = useParams();
  const { data: authorData, isLoading: authorLoading } = useAuthor(slug);

  if (authorLoading) return <LoadingSpinner label="Loading author" />;
  const author = authorData?.data;
  const stories = author?.stories?.data ?? [];
  if (!author) return null;

  return (
    <Container className="py-6">
      <div className="flex items-center gap-4">
        <span className="grid h-16 w-16 place-items-center rounded-full bg-teal-700 text-xl font-semibold text-parchment-50">
          {author.display_name?.[0]?.toUpperCase()}
        </span>
        <div>
          <h1 className="font-display text-2xl font-semibold text-ink-950">{author.display_name}</h1>
          <p className="text-sm text-ink-500">{author.bio}</p>
        </div>
      </div>

      <div className="mt-4">
        <TipWidget penNameSlug={slug} displayName={author.display_name} />
      </div>

      <h2 className="mb-3 mt-8 font-display text-lg font-semibold text-ink-950">Stories</h2>
      {stories.length ? (
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4">
          {stories.map((s) => <StoryCard key={s.id} story={s} />)}
        </div>
      ) : (
        <EmptyState title="No published stories yet" />
      )}
    </Container>
  );
}
