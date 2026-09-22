import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Tracks how far down the episode <article> the reader has scrolled and
 * posts it to /stories/{slug}/episodes/{n}/progress in the background,
 * throttled to at most once every few seconds - not on every scroll tick.
 * Guests just read; nothing is tracked/posted for them (no account to
 * attach progress to).
 */
Alpine.data('episodeReader', ({ story, episode, csrf, authed }) => ({
    lastSent: 0,
    lastLoggedAt: 0,
    ticking: false,

    init() {
        if (!authed) return;

        window.addEventListener('scroll', () => {
            if (this.ticking) return;
            this.ticking = true;
            requestAnimationFrame(() => {
                this.reportProgress();
                this.ticking = false;
            });
        }, { passive: true });
    },

    currentPercent() {
        const doc = document.documentElement;
        const scrollable = doc.scrollHeight - doc.clientHeight;
        if (scrollable <= 0) return 100;
        return Math.min(100, Math.max(0, Math.round((window.scrollY / scrollable) * 100)));
    },

    reportProgress() {
        const percent = this.currentPercent();
        const now = Date.now();

        // At most once every 4s, and only when it actually moved forward.
        if (percent <= this.lastSent || now - this.lastLoggedAt < 4000) return;

        this.lastSent = percent;
        this.lastLoggedAt = now;

        fetch(`/stories/${story}/episodes/${episode}/progress`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ percent }),
            keepalive: true,
        }).catch(() => {
            // Best-effort - a dropped progress ping isn't worth surfacing to the reader.
        });
    },
}));

Alpine.start();
