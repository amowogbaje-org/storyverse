/** Storyverse design tokens.
 * Signature: episodes read like a bookmark ribbon (progress "spine" on episode rows,
 * a wax-seal ring for story-level progress that only appears once 20% is read).
 */
export default {
  content: ["./index.html", "./src/**/*.{js,jsx}"],
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        // ink/parchment are backed by CSS variables (see src/index.css) that
        // flip between :root and .dark - so every existing bg-parchment-50 /
        // text-ink-950 usage across the app becomes theme-aware automatically,
        // without touching each component's className.
        ink: {
          950: "rgb(var(--color-ink-950) / <alpha-value>)",
          900: "rgb(var(--color-ink-900) / <alpha-value>)",
          700: "rgb(var(--color-ink-700) / <alpha-value>)",
          500: "rgb(var(--color-ink-500) / <alpha-value>)",
          300: "rgb(var(--color-ink-300) / <alpha-value>)",
        },
        parchment: {
          50: "rgb(var(--color-parchment-50) / <alpha-value>)",
          100: "rgb(var(--color-parchment-100) / <alpha-value>)",
          200: "rgb(var(--color-parchment-200) / <alpha-value>)",
        },
        gold: {
          400: "#D4AF56",
          500: "#C0983D",
          600: "#9C7A2E",
        },
        teal: {
          500: "#3C8677",
          700: "#2B6357",
          900: "#193B34",
        },
        ribbon: {
          500: "#A63446",
          600: "#872737",
        },
      },
      fontFamily: {
        display: ['"Fraunces"', "serif"],
        sans: ['"Inter"', "system-ui", "sans-serif"],
        mono: ['"IBM Plex Mono"', "monospace"],
        // Literata is a Google-designed text face built specifically for
        // long-form on-screen reading (it's what Google Play Books uses for
        // body copy) - a better fit for episode content than Inter (a UI
        // sans) or Fraunces (a display serif tuned for headline sizes).
        reading: ['"Literata"', "Georgia", "serif"],
      },
      boxShadow: {
        card: "0 1px 2px rgba(20,16,31,0.06), 0 8px 24px -12px rgba(20,16,31,0.25)",
      },
      borderRadius: {
        card: "10px",
      },
    },
  },
  plugins: [],
};
