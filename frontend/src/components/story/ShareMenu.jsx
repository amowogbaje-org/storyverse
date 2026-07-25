import { useEffect, useRef, useState } from "react";
import { useShareStory } from "../../hooks/mutations/useInteractions";
import { randomShareText } from "../../utils/shareContent";

const PLATFORMS = [
  { id: "copy_link", label: "Copy link", swatch: "bg-ink-950", glyph: "🔗" },
  { id: "whatsapp", label: "WhatsApp", swatch: "bg-[#25D366]", glyph: "🟢" },
  { id: "facebook", label: "Facebook", swatch: "bg-[#1877F2]", glyph: "f" },
  { id: "twitter", label: "X / Twitter", swatch: "bg-ink-950", glyph: "𝕏" },
  { id: "linkedin", label: "LinkedIn", swatch: "bg-[#0A66C2]", glyph: "in" },
  { id: "telegram", label: "Telegram", swatch: "bg-[#26A5E4]", glyph: "✈" },
  { id: "email", label: "Email", swatch: "bg-ink-700", glyph: "✉" },
];

function buildShareUrl(platform, url, text) {
  const u = encodeURIComponent(url);
  const t = encodeURIComponent(text);
  switch (platform) {
    case "whatsapp":
      return `https://wa.me/?text=${t}%20${u}`;
    case "facebook":
      return `https://www.facebook.com/sharer/sharer.php?u=${u}&quote=${t}`;
    case "twitter":
      return `https://twitter.com/intent/tweet?text=${t}&url=${u}`;
    case "linkedin":
      return `https://www.linkedin.com/sharing/share-offsite/?url=${u}`;
    case "telegram":
      return `https://t.me/share/url?url=${u}&text=${t}`;
    case "email":
      return `mailto:?subject=${encodeURIComponent("You need to read this")}&body=${t}%0A%0A${u}`;
    default:
      return null;
  }
}

/**
 * @param {string} slug - story slug, used both for the share link and the
 *   /stories/{slug}/share tracking call
 * @param {string} title - story title, used to compose the catchy share text
 * @param {number|null} episodeNumber - set when sharing a specific episode
 *   (episode list row, or the reader page) rather than the story as a whole
 * @param {number} sharesCount - current count, shown next to the button
 * @param {"button"|"icon"} variant - "icon" for compact spots like an episode row
 */
export default function ShareMenu({ slug, title, episodeNumber = null, sharesCount, variant = "button" }) {
  const [open, setOpen] = useState(false);
  const [copied, setCopied] = useState(false);
  const ref = useRef(null);
  const share = useShareStory(slug);

  useEffect(() => {
    function onClickOutside(e) {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    }
    document.addEventListener("mousedown", onClickOutside);
    return () => document.removeEventListener("mousedown", onClickOutside);
  }, []);

  const url = `${window.location.origin}/stories/${slug}${episodeNumber ? `/episodes/${episodeNumber}` : ""}`;

  function track(platform) {
    share.mutate({ platform, episodeNumber: episodeNumber ?? undefined });
  }

  async function handlePlatform(platform) {
    const text = randomShareText(title, episodeNumber);

    if (platform === "copy_link") {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    } else {
      window.open(buildShareUrl(platform, url, text), "_blank", "noopener,noreferrer,width=600,height=500");
      setOpen(false);
    }
    track(platform);
  }

  async function handleNativeShare() {
    const text = randomShareText(title, episodeNumber);
    try {
      await navigator.share({ title, text, url });
      track("native");
    } catch {
      // User backed out of the native share sheet - not an error, don't track it.
    }
    setOpen(false);
  }

  return (
    <div ref={ref} className="relative inline-block">
      <button
        type="button"
        onClick={() => {
          if (navigator.share && variant === "icon") {
            handleNativeShare();
          } else {
            setOpen((o) => !o);
          }
        }}
        className={
          variant === "icon"
            ? "flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-ink-500 hover:bg-ink-950/5 hover:text-ink-900"
            : "inline-flex items-center gap-1.5 rounded-full border border-ink-950/15 px-4 py-1.5 text-sm font-medium text-ink-700 hover:border-ink-950/30"
        }
        aria-label="Share"
      >
        <svg viewBox="0 0 24 24" className="h-4 w-4" fill="none" stroke="currentColor" strokeWidth="1.8">
          <circle cx="18" cy="5" r="3" />
          <circle cx="6" cy="12" r="3" />
          <circle cx="18" cy="19" r="3" />
          <path d="M8.6 10.5 15.4 6.5 M8.6 13.5 15.4 17.5" strokeLinecap="round" />
        </svg>
        {variant === "button" && <span>Share{typeof sharesCount === "number" ? ` (${sharesCount})` : ""}</span>}
      </button>

      {open && (
        <div className="absolute right-0 z-20 mt-2 w-56 rounded-card border border-ink-950/10 bg-white p-1.5 shadow-card">
          {navigator.share && (
            <button
              onClick={handleNativeShare}
              className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm text-ink-700 hover:bg-parchment-100"
            >
              <span className="flex h-6 w-6 items-center justify-center rounded-full bg-ink-950 text-xs text-parchment-50">⤴</span>
              More options
            </button>
          )}
          {PLATFORMS.map((p) => (
            <button
              key={p.id}
              onClick={() => handlePlatform(p.id)}
              className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm text-ink-700 hover:bg-parchment-100"
            >
              <span className={`flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold text-white ${p.swatch}`}>
                {p.glyph}
              </span>
              {p.id === "copy_link" && copied ? "Copied!" : p.label}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
