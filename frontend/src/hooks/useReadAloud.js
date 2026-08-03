import { useCallback, useEffect, useRef, useState } from "react";

/**
 * Wraps the browser's built-in SpeechSynthesis API (window.speechSynthesis).
 * Free and works with zero extra packages/cost, which is why it's the
 * starting point - voice quality is whatever the visitor's OS/browser
 * ships with (varies a lot, and some platforms have no voices at all).
 *
 * Upgrade path: to add a paid, consistent AI voice later (e.g. ElevenLabs or
 * OpenAI's TTS API), swap this hook's internals to call that API from the
 * backend (never expose a TTS provider's key to the browser), stream back
 * audio, and play it with a plain <audio> element instead of
 * speechSynthesis - the onEnd/play/pause/stop contract below can stay the
 * same, so EpisodeReaderPage wouldn't need to change.
 */
export function useReadAloud(text, { onEnd } = {}) {
  const [supported] = useState(() => typeof window !== "undefined" && "speechSynthesis" in window);
  const [speaking, setSpeaking] = useState(false);
  const [paused, setPaused] = useState(false);
  const utteranceRef = useRef(null);
  const onEndRef = useRef(onEnd);
  onEndRef.current = onEnd;

  const stop = useCallback(() => {
    if (!supported) return;
    window.speechSynthesis.cancel();
    setSpeaking(false);
    setPaused(false);
  }, [supported]);

  const play = useCallback(() => {
    if (!supported || !text) return;

    // Resuming a paused utterance vs. starting fresh are different calls in
    // this API - cancel() clears state entirely, so only use it when we're
    // not just unpausing.
    if (paused) {
      window.speechSynthesis.resume();
      setPaused(false);
      setSpeaking(true);
      return;
    }

    window.speechSynthesis.cancel(); // clear anything left over from a previous episode
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.onend = () => {
      setSpeaking(false);
      setPaused(false);
      onEndRef.current?.();
    };
    utterance.onerror = () => {
      setSpeaking(false);
      setPaused(false);
    };
    utteranceRef.current = utterance;
    window.speechSynthesis.speak(utterance);
    setSpeaking(true);
    setPaused(false);
  }, [supported, text, paused]);

  const pause = useCallback(() => {
    if (!supported) return;
    window.speechSynthesis.pause();
    setPaused(true);
    setSpeaking(false);
  }, [supported]);

  // Stop speaking on unmount (e.g. navigating away mid-episode) so audio
  // doesn't keep playing over a page the reader has already left.
  useEffect(() => () => { if (supported) window.speechSynthesis.cancel(); }, [supported]);

  return { supported, speaking, paused, play, pause, stop };
}
