// Mirrors backend/app/Services/StoryAccessService.php.
// This is UX-only gating — the API is the real enforcement point, this just
// lets the reader UI show locks/upsells without a round trip per episode.
const GUEST_LIMIT = 2;
const REGISTERED_PREMIUM_LIMIT = 5;

/** @returns {number|null} null = unlimited, otherwise max episode_number visible */
export function accessibleEpisodeLimit(story, user) {
  if (user?.has_active_premium_subscription) return null;
  if (!user) return GUEST_LIMIT;
  return story.access_type === "free" ? null : REGISTERED_PREMIUM_LIMIT;
}

export function canAccessEpisode(story, episode, user) {
  const limit = accessibleEpisodeLimit(story, user);
  return limit === null || episode.episode_number <= limit;
}

/** 'guest_limit' -> prompt to register, 'premium_required' -> prompt to subscribe, null -> unlocked */
export function lockReason(story, episode, user) {
  if (canAccessEpisode(story, episode, user)) return null;
  return user ? "premium_required" : "guest_limit";
}
