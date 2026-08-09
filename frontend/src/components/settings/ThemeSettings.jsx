import { useTheme } from "../../context/ThemeContext";

const OPTIONS = [
  { value: "light", label: "Light", hint: "Parchment background, dark ink text." },
  { value: "dark", label: "Dark", hint: "Easier on the eyes for night reading." },
];

export default function ThemeSettings() {
  const { theme, setTheme } = useTheme();

  return (
    <div className="mt-4 rounded-card border border-ink-950/10 bg-white/40 p-4 dark:border-parchment-100/10 dark:bg-ink-900/40">
      <h2 className="font-display text-lg font-semibold text-ink-950 dark:text-parchment-100">Appearance</h2>
      <p className="mt-1 text-sm text-ink-500 dark:text-parchment-300">
        Choose how Storyverse looks on this device. You can also switch it from the icon in the header.
      </p>
      <div className="mt-3 grid grid-cols-2 gap-2">
        {OPTIONS.map((opt) => (
          <button
            key={opt.value}
            type="button"
            onClick={() => setTheme(opt.value)}
            aria-pressed={theme === opt.value}
            className={`rounded-card border p-3 text-left transition ${
              theme === opt.value
                ? "border-gold-500 bg-gold-400/10"
                : "border-ink-950/10 hover:border-ink-950/25 dark:border-parchment-100/10 dark:hover:border-parchment-100/25"
            }`}
          >
            <div className="text-sm font-medium text-ink-950 dark:text-parchment-100">{opt.label}</div>
            <div className="mt-0.5 text-xs text-ink-500 dark:text-parchment-300">{opt.hint}</div>
          </button>
        ))}
      </div>
    </div>
  );
}
