import { useState } from "react";

/**
 * Drop-in replacement for a plain `<input type="password">` with an eye
 * icon toggle. Kept dependency-free (inline SVGs) since the rest of the
 * app doesn't pull in an icon package.
 */
export default function PasswordInput({
  value,
  onChange,
  placeholder = "Password",
  autoComplete = "current-password",
  required = true,
  className = "",
}) {
  const [visible, setVisible] = useState(false);

  return (
    <div className="relative">
      <input
        type={visible ? "text" : "password"}
        required={required}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        autoComplete={autoComplete}
        className={`w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 pr-11 text-sm outline-none focus:border-gold-500 ${className}`}
      />
      <button
        type="button"
        onClick={() => setVisible((v) => !v)}
        tabIndex={-1}
        aria-label={visible ? "Hide password" : "Show password"}
        className="absolute right-3 top-1/2 -translate-y-1/2 text-ink-500 hover:text-ink-900"
      >
        {visible ? (
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
            <circle cx="12" cy="12" r="3" />
          </svg>
        ) : (
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <path d="M3 3l18 18" />
            <path d="M10.6 10.6a2 2 0 0 0 2.83 2.83" />
            <path d="M9.4 5.4A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a13.2 13.2 0 0 1-3.17 3.98M6.6 6.6C4.06 8.14 2 12 2 12s3.5 7 10 7a9.6 9.6 0 0 0 3.4-.6" />
          </svg>
        )}
      </button>
    </div>
  );
}
