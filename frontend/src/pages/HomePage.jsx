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
import Swiper from "../components/common/Swiper";

function StoryRow({ stories }) {
  return (
    <Swiper>
      {stories.map((s) => (
        <div key={s.id} className="w-36 shrink-0 snap-start sm:w-44">
          <StoryCard story={s} />
        </div>
      ))}
    </Swiper>
  );
}

// Soft pastel circle + matching icon-tint pairs, cycled through for genre
// chips and the "why Storyverse" icons - mirrors the reference mockup's
// rotating pink/gold/sky/violet palette without hardcoding specific colors
// to specific genre names (the real genre list is dynamic, backend-driven).
const ACCENTS = [
  { bg: "bg-ribbon-500/10", text: "text-ribbon-600" },
  { bg: "bg-gold-500/15", text: "text-gold-600" },
  { bg: "bg-sky-500/10", text: "text-sky-600" },
  { bg: "bg-teal-500/10", text: "text-teal-700" },
  { bg: "bg-violet-500/10", text: "text-violet-600" },
];

function accentFor(index) {
  return ACCENTS[index % ACCENTS.length];
}

const WHY_STORYVERSE = [
  { icon: "📖", title: "Read", body: "Dive into any story from Episode 1, completely free." },
  { icon: "🔖", title: "Save", body: "Keep your favorite stories saved in one place." },
  { icon: "🔔", title: "Return", body: "Get notified the moment a story you follow continues." },
  { icon: "💬", title: "Discuss", body: "Join the conversation on episodes you love." },
];

// Best-effort emoji per genre name for the genre strip below. Falls back to
// a generic book emoji for anything unmapped so new genres never render
// blank.
const GENRE_EMOJI = {
  romance: "❤️", drama: "🎭", business: "💼", political: "🏛️", politics: "🏛️",
  mystery: "🔍", thriller: "🕵️", fantasy: "✨", inspirational: "📖",
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
  const newStories = newData?.data ?? [];
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

      {/* Hero — real product photography, now with a dark gradient overlay
          so the copy can sit in solid white/parchment instead of dark ink
          text competing against the photo's own colors underneath it. */}
      <section className="relative mb-10 overflow-hidden rounded-card bg-ink-950">
        <div
          className="absolute inset-0 bg-cover bg-[position:70%_25%]"
          style={{ backgroundImage: "url(/images/hero-woman.jpg)" }}
          aria-hidden="true"
        />
        {/* Darkest behind the text (left), lighter toward the photo's own
            right side, so her face stays visible while the copy stays readable. */}
        <div
          className="absolute inset-0 bg-gradient-to-r from-black/80 via-black/60 to-black/20"
          aria-hidden="true"
        />
        <div className="relative px-6 py-10 sm:px-10 sm:py-14">
          <p className="font-mono text-xs uppercase tracking-widest text-gold-400">Serialized fiction, one episode at a time</p>
          <h1 className="mt-3 max-w-md font-display text-3xl font-semibold text-white sm:text-4xl">
            Your next favorite story is here.
          </h1>
          <p className="mt-3 max-w-sm text-sm text-parchment-100/90 sm:text-base">
            Read original stories, one episode at a time. Follow your favorites and never lose your place.
          </p>
          <div className="mt-6 flex flex-wrap items-center gap-3">
            <Link
              to="/browse"
              className="rounded-full bg-gold-500 px-5 py-2.5 text-sm font-semibold text-ink-950 hover:bg-gold-400"
            >
              Start Reading Free →
            </Link>
          </div>
          <p className="mt-4 flex items-center gap-1.5 text-xs text-parchment-100/70">
            <span aria-hidden="true">ⓘ</span> No account needed for the first episodes free.
          </p>
        </div>
      </section>

      {/* Featured story */}
      {featured && (
        <section className="mb-10">
          <p className="mb-3 text-xs font-semibold uppercase tracking-widest text-gold-600">Featured Story</p>
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
        <SectionHeader title="Trending now" viewAllHref="/browse?sort=popular" />
        {popularLoading ? <LoadingSpinner /> : <StoryRow stories={popularStories} />}
      </section>

      {/* Genres — circular pastel icon avatars, one per accent color, hidden
          entirely unless there's more than one genre actually in use across
          published stories (a single-option filter isn't a real choice, and
          the backend already excludes unused genres). */}
      {genres.length > 1 && (
        <section className="mb-10">
          <SectionHeader title="Find your next story" subtitle="Explore stories across different genres" />
          <Swiper>
            {genres.map((g, i) => {
              const accent = accentFor(i);
              return (
                <Link
                  key={g.slug}
                  to={`/browse?genre=${encodeURIComponent(g.slug)}`}
                  className="flex w-16 shrink-0 snap-start flex-col items-center gap-2 text-center sm:w-20"
                >
                  <span className={`flex h-14 w-14 items-center justify-center rounded-full text-2xl ${accent.bg}`}>
                    {genreEmoji(g.name)}
                  </span>
                  <span className="text-xs font-medium text-ink-700">{g.name}</span>
                </Link>
              );
            })}
          </Swiper>
        </section>
      )}

      {/* Make Storyverse yours — account pitch, shown only to guests, and
          only after they've already seen what there is to sign up for. The
          "library" side is a fanned collage of real trending covers rather
          than stock photography, so it needs no external image asset and
          stays truthful to what's actually on the platform. */}
      {!isAuthenticated && (
        <section className="mb-10 overflow-hidden rounded-card border border-gold-500/40 bg-gold-400/10">
          <div className="flex flex-col items-center gap-6 p-6 sm:flex-row sm:p-10">
            {popularStories.length > 0 && (
              <div className="flex shrink-0 -space-x-8">
                {popularStories.slice(0, 3).map((s, i) => (
                  <div
                    key={s.id}
                    className="h-28 w-20 overflow-hidden rounded-card border-2 border-parchment-50 bg-ink-900 shadow-card"
                    style={{ transform: `rotate(${(i - 1) * 8}deg)`, zIndex: 3 - i }}
                  >
                    {s.cover_image_url && (
                      <img src={s.cover_image_url} alt="" className="h-full w-full object-cover" />
                    )}
                  </div>
                ))}
              </div>
            )}
            <div className="text-center sm:text-left">
              <h2 className="font-display text-xl font-semibold text-ink-950 sm:text-2xl">Make Storyverse yours</h2>
              <p className="mx-auto mt-2 max-w-md text-sm text-ink-700 sm:mx-0">
                Create a free account to follow your favorite stories, save your reading progress, get notified
                about new episodes, and build your personal library.
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
            </div>
          </div>
        </section>
      )}

      {/* New releases */}
      <section className="mb-10">
        <SectionHeader title="New releases" viewAllHref="/browse?sort=new" />
        {newLoading ? <LoadingSpinner /> : <StoryRow stories={newStories} />}
      </section>

      {/* Why Storyverse — same circular icon-avatar treatment as the genre strip */}
      <section className="mb-10">
        <SectionHeader title="Why Storyverse?" subtitle="Everything you need to keep a story going" />
        <div className="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-4">
          {WHY_STORYVERSE.map((item, i) => {
            const accent = accentFor(i);
            return (
              <div key={item.title} className="text-center sm:text-left">
                <span className={`mx-auto flex h-14 w-14 items-center justify-center rounded-full text-2xl sm:mx-0 ${accent.bg}`}>
                  {item.icon}
                </span>
                <p className="mt-3 text-sm font-semibold text-ink-950">{item.title}</p>
                <p className="mt-1 text-xs text-ink-500">{item.body}</p>
              </div>
            );
          })}
        </div>
      </section>

      {/* Final CTA — mirrors the hero's photo-bleed treatment (dark panel
          here instead of cream, photo bleeding from the left instead of the
          right) so the page opens and closes on the same real-photography
          language rather than a flat color block or a story cover. */}
      <section className="relative mb-4 overflow-hidden rounded-card bg-ink-950 text-parchment-50">
        <div
          className="absolute inset-y-0 left-0 w-2/3 bg-cover bg-[position:50%_20%] opacity-80 [mask-image:linear-gradient(to_left,transparent,black_35%)]"
          style={{ backgroundImage: "url(/images/cta-man.jpg)" }}
          aria-hidden="true"
        />
        <div className="relative px-6 py-10 text-center sm:px-10 sm:py-14 sm:text-right">
          <h2 className="font-display text-xl font-semibold sm:text-2xl">Your next favorite story is waiting.</h2>
          <p className="mt-2 text-sm text-ink-300">Start reading for free.</p>
          <Link
            to="/browse"
            className="mt-5 inline-block rounded-full bg-gold-500 px-6 py-2.5 text-sm font-semibold text-ink-950 hover:bg-gold-400"
          >
            Explore Stories →
          </Link>
        </div>
      </section>
    </Container>
  );
}
