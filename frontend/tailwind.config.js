/** Storyverse design tokens.
 * Signature: episodes read like a bookmark ribbon (progress "spine" on episode rows,
 * a wax-seal ring for story-level progress that only appears once 20% is read).
 */
export default {
  content: ["./index.html", "./src/**/*.{js,jsx}"],
  theme: {
    extend: {
      colors: {
        ink: {
          950: "#14101F",
          900: "#1B1230",
          700: "#372C4C",
          500: "#5B4D75",
          300: "#8B7FA0",
        },
        parchment: {
          50: "#F9F6EE",
          100: "#F1ECDF",
          200: "#E6DFCC",
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
