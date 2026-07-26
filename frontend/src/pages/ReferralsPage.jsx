import { useState } from "react";
import { useAuth } from "../context/AuthContext";
import { useMyReferrals } from "../hooks/queries/useReferrals";
import Container from "../components/common/Container";
import LoadingSpinner from "../components/common/LoadingSpinner";

const SHARE_TEXT = "Come read on Storyverse with me — sign up with my link and we both win:";

function shareUrlFor(platform, link) {
  const u = encodeURIComponent(link);
  const t = encodeURIComponent(SHARE_TEXT);
  switch (platform) {
    case "whatsapp":
      return `https://wa.me/?text=${t}%20${u}`;
    case "facebook":
      return `https://www.facebook.com/sharer/sharer.php?u=${u}&quote=${t}`;
    case "twitter":
      return `https://twitter.com/intent/tweet?text=${t}&url=${u}`;
    case "telegram":
      return `https://t.me/share/url?url=${u}&text=${t}`;
    default:
      return null;
  }
}

const PLATFORMS = [
  { id: "whatsapp", label: "WhatsApp", swatch: "bg-[#25D366]", glyph: "🟢" },
  { id: "facebook", label: "Facebook", swatch: "bg-[#1877F2]", glyph: "f" },
  { id: "twitter", label: "X / Twitter", swatch: "bg-ink-950", glyph: "𝕏" },
  { id: "telegram", label: "Telegram", swatch: "bg-[#26A5E4]", glyph: "✈" },
];

export default function ReferralsPage() {
  const { isAuthenticated } = useAuth();
  const { data, isLoading } = useMyReferrals(isAuthenticated);
  const [copied, setCopied] = useState(false);

  const stats = data?.data;

  if (!isAuthenticated) {
    return (
      <Container className="max-w-lg py-14 text-center">
        <p className="text-sm text-ink-500">Sign in to get your referral link.</p>
      </Container>
    );
  }

  if (isLoading || !stats) {
    return (
      <Container className="py-14">
        <LoadingSpinner />
      </Container>
    );
  }

  const progressInTier = stats.per_tier - stats.remaining_to_next_reward;

  async function copyLink() {
    await navigator.clipboard.writeText(stats.referral_link);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 1500);
  }

  return (
    <Container className="max-w-lg py-10 pb-24">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Refer friends, earn premium</h1>
      <p className="mt-1 text-sm text-ink-500">
        Every 10 friends who sign up with your link and verify their email earns you a free month
        of premium - and it keeps going: 20 friends is 2 months, 30 is 3, and so on.
      </p>

      <div className="mt-6 rounded-card border border-gold-500/40 bg-gold-400/10 p-4">
        <div className="flex items-center justify-between text-sm">
          <span className="font-medium text-ink-950">
            {stats.verified_count} verified referral{stats.verified_count === 1 ? "" : "s"}
          </span>
          <span className="text-ink-600">{stats.remaining_to_next_reward} more to your next free month</span>
        </div>
        <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-ink-950/10">
          <div
            className="h-full rounded-full bg-gold-500"
            style={{ width: `${(progressInTier / stats.per_tier) * 100}%` }}
          />
        </div>
        {stats.tiers_claimed > 0 && (
          <p className="mt-2 text-xs text-ink-600">
            You've already earned {stats.tiers_claimed} free month{stats.tiers_claimed === 1 ? "" : "s"} this way. 🎉
          </p>
        )}
        {stats.pending_count > 0 && (
          <p className="mt-1 text-xs text-ink-500">
            {stats.pending_count} more {stats.pending_count === 1 ? "signup hasn't" : "signups haven't"} verified their email yet.
          </p>
        )}
      </div>

      <div className="mt-5 rounded-card border border-ink-950/10 bg-white/60 p-4">
        <p className="text-sm font-medium text-ink-950">Your referral link</p>
        <div className="mt-2 flex gap-2">
          <input
            readOnly value={stats.referral_link}
            className="min-w-0 flex-1 truncate rounded-card border border-ink-950/15 bg-parchment-50 px-3 py-2 text-xs text-ink-700"
          />
          <button
            onClick={copyLink}
            className="shrink-0 rounded-card bg-ink-950 px-4 py-2 text-xs font-medium text-parchment-50"
          >
            {copied ? "Copied!" : "Copy"}
          </button>
        </div>

        <div className="mt-3 flex gap-2">
          {PLATFORMS.map((p) => (
            <button
              key={p.id}
              onClick={() => window.open(shareUrlFor(p.id, stats.referral_link), "_blank", "noopener,noreferrer,width=600,height=500")}
              className={`flex h-9 w-9 items-center justify-center rounded-full text-xs font-semibold text-white ${p.swatch}`}
              aria-label={`Share on ${p.label}`}
              title={`Share on ${p.label}`}
            >
              {p.glyph}
            </button>
          ))}
        </div>
      </div>

      {stats.referrals.length > 0 && (
        <div className="mt-5">
          <p className="mb-2 text-sm font-medium text-ink-950">Your referrals</p>
          <ul className="divide-y divide-ink-950/8 rounded-card border border-ink-950/10 bg-white/40">
            {stats.referrals.map((r, i) => (
              <li key={i} className="flex items-center justify-between px-4 py-2.5 text-sm">
                <span className="text-ink-900">{r.name}</span>
                <span className={r.verified ? "text-xs font-medium text-teal-700" : "text-xs text-ink-400"}>
                  {r.verified ? "Verified" : "Awaiting verification"}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </Container>
  );
}
