const STORY_HOOKS = [
  (title) => `Don't start reading "${title}" if you don't want to get addicted 👀`,
  (title) => `I couldn't put "${title}" down. Consider yourself warned.`,
  (title) => `Currently obsessed with "${title}" — read it before everyone else does.`,
  (title) => `"${title}" lived in my head rent-free all night. Your turn.`,
  (title) => `Found my next obsession: "${title}". You're welcome.`,
  (title) => `Not me staying up till 3am for "${title}"... okay it was me.`,
  (title) => `"${title}" is the reason I'm behind on everything this week. No regrets.`,
];

const EPISODE_HOOKS = [
  (title, ep) => `Episode ${ep} of "${title}" just wrecked me — catch up before I spoil it.`,
  (title, ep) => `"${title}" episode ${ep} is unhinged (affectionate). Read it now.`,
  (title, ep) => `Don't scroll past this — episode ${ep} of "${title}" is too good to skip.`,
  (title, ep) => `I need someone else to read episode ${ep} of "${title}" RIGHT NOW so we can talk.`,
];

export function randomShareText(title, episodeNumber = null) {
  const pool = episodeNumber ? EPISODE_HOOKS : STORY_HOOKS;
  const pick = pool[Math.floor(Math.random() * pool.length)];
  return episodeNumber ? pick(title, episodeNumber) : pick(title);
}
