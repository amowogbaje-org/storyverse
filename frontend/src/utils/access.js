// Mirrors backend/app/Services/StoryAccessService.php.
// This is UX-only gating — the API is the real enforcement point, this just
// lets the reader UI show locks/upsells without a round trip per episode.
//
// `limits` is NOT hardcoded here - it comes from GET /platform-status
// (guest_episode_limit / registered_premium_episode_limit, read straight
// from GUEST_EPISODE_LIMIT / REGISTERED_PREMIUM_EPISODE_LIMIT in the
// backend's .env) via useAccessLimits() in hooks/queries/usePlatformStatus.js.
// A previous version of this file hardcoded its own copy of these numbers,
// which is exactly the kind of drift that made the UI keep showing old
// limits after the backend .env was updated - callers now pass the live
// values in instead.

/** @returns {number|null} null = unlimited, otherwise max episode_number visible */
export function accessibleEpisodeLimit(story, user, limits) {
  if (user?.has_active_premium_subscription) return null;
  if (!user) return limits.guestEpisodeLimit;
  return story.access_type === "free" ? null : limits.registeredPremiumEpisodeLimit;
}

export function canAccessEpisode(story, episode, user, limits) {
  const limit = accessibleEpisodeLimit(story, user, limits);
  return limit === null || episode.episode_number <= limit;
}

/** 'guest_limit' -> prompt to register, 'premium_required' -> prompt to subscribe, null -> unlocked */
export function lockReason(story, episode, user, limits) {
  if (canAccessEpisode(story, episode, user, limits)) return null;
  return user ? "premium_required" : "guest_limit";
}
