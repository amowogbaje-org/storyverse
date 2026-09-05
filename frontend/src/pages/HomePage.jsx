import { Link } from "react-router-dom";
import { useNewReleases, usePopularStories, useGenres } from "../hooks/queries/useStories";
import { useAuth } from "../context/AuthContext";
import StoryCard from "../components/story/StoryCard";
import SectionHeader from "../components/common/SectionHeader";
import LoadingSpinner from "../components/common/LoadingSpinner";
import Container from "../components/common/Container";
import WelcomeBanner from "../components/common/WelcomeBanner";
import Seo from "../components/common/Seo";
import NextBadges from "../components/badges/NextBadges";

function StoryRow({ stories }) {
  return (
    <div className="-mx-4 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-3 sm:gap-4 sm:overflow-visible sm:px-0 md:grid-cols-4 lg:grid-cols-5">
      {stories.map((s) => (
        <div key={s.id} className="w-36 shrink-0 sm:w-auto">
          <StoryCard story={s} />
        </div>
      ))}
    </div>
  );
}

const WHY_STORYVERSE = [
  { icon: "📖", title: "Start reading", body: "Dive into any story from Episode 1, completely free." },
  { icon: "❤️", title: "Follow stories", body: "Keep your favorites together in one place." },
  { icon: "🔔", title: "Never miss an episode", body: "Get notified the moment the story continues." },
  { icon: "🔖", title: "Pick up where you left off", body: "Your reading progress stays with you." },
];

// Best-effort emoji per genre name for the "find your next obsession" strip.
// Falls back to a generic book emoji for anything unmapped so new genres
// never render blank.
const GENRE_EMOJI = {
  romance: "❤️", drama: "🔥", business: "💼", political: "🏛️", politics: "🏛️",
  mystery: "🕵️", thriller: "🕵️", fantasy: "✨", inspirational: "📖",
  comedy: "😂", horror: "👻", "sci-fi": "🚀", "science fiction": "🚀",
  crime: "🔍", family: "🏡", adventure: "🧭",
};

function genreEmoji(name = "") {
  return GENRE_EMOJI[name.trim().toLowerCase()] ?? "📖";
}

export default function HomePage() {
  const { isAuthenticated } = useAuth();
  const { data: newData, isLoading: newLoading } = useNewReleases();
  const { data: popularData, isLoading: popularLoading } = usePopularStories();
  const { data: genresData } = useGenres();

  const popularStories = popularData?.data ?? [];
  const genres = genresData?.data ?? [];
  // No dedicated "featured story" endpoint exists yet, so the top trending
  // story stands in as the featured pick - it's already the platform's best
  // signal of "people are reading this right now".
  const featured = popularStories[0];

  return (
    <Container className="py-6">
      <Seo path="/" />
      <WelcomeBanner />
      <NextBadges variant="compact" limit={1} />

      {/* Hero */}
      <section className="mb-10 overflow-hidden rounded-card bg-ink-950 px-6 py-10 text-parchment-50 sm:px-10 sm:py-14">
        <p className="font-mono text-xs uppercase tracking-widest text-gold-400">Serialized fiction, one episode at a time</p>
        <h1 className="mt-3 max-w-lg font-display text-3xl font-semibold sm:text-4xl">
          Stories that keep you coming back.
        </h1>
        <p className="mt-3 max-w-md text-sm text-ink-300 sm:text-base">
          Discover original stories, follow the ones you love, and never lose your place.
        </p>
        <div className="mt-6 flex flex-wrap items-center gap-3">
          <Link
            to="/browse"
            className="rounded-full bg-gold-500 px-5 py-2.5 text-sm font-semibold text-ink-950 hover:bg-gold-400"
          >
            Start Reading Free
          </Link>
          <Link
            to="/browse"
            className="rounded-full border border-parchment-50/30 px-5 py-2.5 text-sm font-medium text-parchment-50 hover:border-parchment-50/60"
          >
            Explore Stories
          </Link>
        </div>
        <p className="mt-4 text-xs text-ink-300">No subscription required. Read the first episodes free.</p>
      </section>

      {/* Featured story */}
      {featured && (
        <section className="mb-10">
          <p className="mb-3 text-xs font-semibold uppercase tracking-widest text-gold-600">🔥 Featured Story</p>
          <Link
            to={`/stories/${featured.slug}`}
            className="group flex flex-col gap-5 overflow-hidden rounded-card border border-ink-950/8 bg-white/60 p-5 shadow-card transition hover:-translate-y-0.5 sm:flex-row sm:items-center"
          >
            <div className="mx-auto h-48 w-32 shrink-0 overflow-hidden rounded-card bg-ink-900 sm:mx-0">
              {featured.cover_image_url && (
                <img
                  src={featured.cover_image_url}
                  alt={featured.title}
                  className="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                />
              )}
            </div>
            <div className="min-w-0 flex-1 text-center sm:text-left">
              <h2 className="font-display text-xl font-semibold text-ink-950 sm:text-2xl">{featured.title}</h2>
              <p className="mt-1 text-xs text-ink-500">{featured.author_display_name}</p>
              <p className="mt-2 line-clamp-2 text-sm text-ink-700">{featured.description}</p>
              <span className="mt-4 inline-block rounded-full bg-ink-950 px-5 py-2 text-sm font-medium text-parchment-50 group-hover:bg-ink-900">
                Read Episode 1
              </span>
            </div>
          </Link>
        </section>
      )}

      {/* Trending */}
      <section className="mb-10">
        <SectionHeader title="🔥 Trending now" viewAllHref="/browse?sort=popular" />
        {popularLoading ? <LoadingSpinner /> : <StoryRow stories={popularStories} />}
      </section>

      {/* Genres */}
      {genres.length > 0 && (
        <section className="mb-10">
          <SectionHeader title="Find your next obsession" />
          <div className="flex flex-wrap gap-2">
            {genres.map((g) => (
              <Link
                key={g.slug}
                to={`/browse?genre=${encodeURIComponent(g.slug)}`}
                className="inline-flex items-center gap-1.5 rounded-full border border-ink-950/10 bg-white/60 px-4 py-2 text-sm font-medium text-ink-700 hover:border-gold-500 hover:text-ink-950"
              >
                <span>{genreEmoji(g.name)}</span> {g.name}
              </Link>
            ))}
          </div>
        </section>
      )}

      {/* Why Storyverse */}
      <section className="mb-10">
        <SectionHeader title="Read. Follow. Return." />
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          {WHY_STORYVERSE.map((item) => (
            <div key={item.title} className="rounded-card border border-ink-950/8 bg-white/40 p-4 text-center">
              <div className="text-2xl">{item.icon}</div>
              <p className="mt-2 text-sm font-semibold text-ink-950">{item.title}</p>
              <p className="mt-1 text-xs text-ink-500">{item.body}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Make Storyverse yours — account pitch, shown only to guests, and only
          after they've already seen what there is to sign up for. */}
      {!isAuthenticated && (
        <section className="mb-10 rounded-card border border-gold-500/40 bg-gold-400/10 p-6 text-center sm:p-10">
          <h2 className="font-display text-xl font-semibold text-ink-950 sm:text-2xl">Make Storyverse yours</h2>
          <p className="mx-auto mt-2 max-w-md text-sm text-ink-700">
            Create a free account to follow your favorite stories, save your reading progress, get notified about
            new episodes, and build your personal library.
          </p>
          <Link
            to="/register"
            className="mt-5 inline-block rounded-full bg-ink-950 px-6 py-2.5 text-sm font-medium text-parchment-50 hover:bg-ink-900"
          >
            Create Free Account
          </Link>
          <p className="mt-3 text-xs text-ink-500">
            Already have an account? <Link to="/login" className="text-teal-700 hover:underline">Sign in</Link>
          </p>
        </section>
      )}

      {/* New releases */}
      <section className="mb-10">
        <SectionHeader title="New releases" viewAllHref="/browse?sort=new" />
        {newLoading ? <LoadingSpinner /> : <StoryRow stories={newData?.data ?? []} />}
      </section>

      {/* Final CTA */}
      <section className="mb-4 rounded-card bg-ink-950 px-6 py-10 text-center text-parchment-50">
        <h2 className="font-display text-xl font-semibold sm:text-2xl">Your next favorite story is waiting.</h2>
        <p className="mt-2 text-sm text-ink-300">Start reading for free.</p>
        <Link
          to="/browse"
          className="mt-5 inline-block rounded-full bg-gold-500 px-6 py-2.5 text-sm font-semibold text-ink-950 hover:bg-gold-400"
        >
          Explore Stories →
        </Link>
      </section>
    </Container>
  );
}
