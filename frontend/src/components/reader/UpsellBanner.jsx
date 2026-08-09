import { Link } from "react-router-dom";
import { useLoginUrl } from "../../hooks/useLoginUrl";

export default function UpsellBanner({ reason, slug }) {
  const loginHref = useLoginUrl();

  if (!reason) return null;

  const copy =
    reason === "guest_limit"
      ? {
          title: "Create a free account to keep reading",
          body: "You've reached the free preview limit. Registered readers get more episodes on every story.",
          cta: "Sign in or register",
          to: loginHref,
        }
      : {
          title: "This episode is for readers who own this book",
          body: "Buy this story to unlock every episode, priced for where you live.",
          cta: "Buy this book",
          to: `/stories/${slug}`,
        };

  return (
    <div className="rounded-card border border-gold-500/40 bg-gold-400/10 p-5 text-center">
      <p className="font-display text-lg text-ink-950">{copy.title}</p>
      <p className="mx-auto mt-1 max-w-sm text-sm text-ink-500">{copy.body}</p>
      <Link
        to={copy.to}
        className="mt-4 inline-block rounded-full bg-ink-950 px-5 py-2 text-sm font-medium text-parchment-50 hover:bg-ink-900"
      >
        {copy.cta}
      </Link>
    </div>
  );
}
