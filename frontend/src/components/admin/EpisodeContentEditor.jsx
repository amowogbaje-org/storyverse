import { useRef, useState } from "react";
import RichText from "../reader/RichText";
import { wrapSelection, insertBlock } from "../../utils/textareaEditing";

const TOOLBAR_BUTTONS = [
  { label: "B", title: "Bold", action: (v, s, e) => wrapSelection(v, s, e, "**", "**", "bold text"), className: "font-bold" },
  { label: "I", title: "Italic", action: (v, s, e) => wrapSelection(v, s, e, "*", "*", "italic text"), className: "italic" },
  { label: "S", title: "Strikethrough", action: (v, s, e) => wrapSelection(v, s, e, "~~", "~~", "struck text"), className: "line-through" },
  { label: "❝", title: "Quote / letter", action: (v, s, e) => wrapSelection(v, s, e, "> ", "", "quoted text") },
  { label: "⁂", title: "Scene break", action: (v, s, e) => insertBlock(v, s, e, "***") },
];

function ToolbarTextarea({ value, onChange, rows = 10 }) {
  const ref = useRef(null);

  function applyAction(action) {
    const el = ref.current;
    if (!el) return;
    const result = action(value, el.selectionStart, el.selectionEnd);
    onChange(result.value);
    requestAnimationFrame(() => {
      el.focus();
      el.setSelectionRange(result.selectionStart, result.selectionEnd);
    });
  }

  return (
    <div>
      <div className="mb-1 flex gap-1 rounded-t-card border border-b-0 border-ink-950/15 bg-parchment-100 p-1">
        {TOOLBAR_BUTTONS.map((btn) => (
          <button
            key={btn.label}
            type="button"
            title={btn.title}
            onClick={() => applyAction(btn.action)}
            className={`grid h-7 w-7 place-items-center rounded text-sm text-ink-700 hover:bg-white ${btn.className ?? ""}`}
          >
            {btn.label}
          </button>
        ))}
      </div>
      <textarea
        ref={ref}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        rows={rows}
        className="w-full rounded-b-card border border-ink-950/15 bg-white px-3 py-2 font-reading text-sm"
      />
    </div>
  );
}

function styledStatusLabel(episode) {
  if (!episode.styled_at) return { text: "Not styled yet — the AI agent styles new episodes within ~15 minutes, or style it manually below.", tone: "pending" };
  return { text: `Styled as of ${new Date(episode.styled_at).toLocaleString()}`, tone: "done" };
}

/**
 * `episode` (optional) - when editing an existing episode, used to show
 * styling status and pre-fill both fields. Omit when creating a new one;
 * new episodes only take raw text (see EpisodeManagementController::store) -
 * there's nothing to manually style yet until it's actually saved once.
 */
export default function EpisodeContentEditor({
  episode, rawValue, onRawChange, styledValue, onStyledChange,
  onSaveRaw, onSaveStyled, savingRaw, savingStyled, error,
}) {
  const [showPreview, setShowPreview] = useState(true);
  const status = episode ? styledStatusLabel(episode) : null;

  return (
    <div className="space-y-4">
      <div>
        <label className="mb-1 block text-xs font-medium text-ink-500">
          Raw text {episode && "(source for CraftProfessor and the AI styling agent)"}
        </label>
        <textarea
          value={rawValue}
          onChange={(e) => onRawChange(e.target.value)}
          rows={8}
          placeholder="Episode text, no formatting needed here"
          className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
        />
        {onSaveRaw && (
          <button type="button" onClick={onSaveRaw} disabled={savingRaw}
            className="mt-2 rounded-full bg-ink-950 px-4 py-1.5 text-xs font-medium text-parchment-50 disabled:opacity-50">
            {savingRaw ? "Saving…" : "Save raw text"}
          </button>
        )}
      </div>

      {episode && (
        <div>
          <div className="mb-1 flex items-center justify-between">
            <label className="block text-xs font-medium text-ink-500">Styled text (what readers see)</label>
            <button type="button" onClick={() => setShowPreview((v) => !v)} className="text-xs text-teal-700 hover:underline">
              {showPreview ? "Hide preview" : "Show preview"}
            </button>
          </div>
          <p className={`mb-2 text-xs ${status.tone === "pending" ? "text-gold-600" : "text-ink-500"}`}>{status.text}</p>

          <div className={showPreview ? "grid gap-3 md:grid-cols-2" : ""}>
            <ToolbarTextarea value={styledValue} onChange={onStyledChange} />
            {showPreview && (
              <div className="rounded-card border border-ink-950/15 bg-parchment-50 p-3">
                <p className="mb-2 text-xs uppercase tracking-wide text-ink-500">Preview</p>
                <RichText text={styledValue} className="font-reading text-sm leading-relaxed text-ink-900" />
              </div>
            )}
          </div>

          <button type="button" onClick={onSaveStyled} disabled={savingStyled}
            className="mt-2 rounded-full border border-gold-500 px-4 py-1.5 text-xs font-medium text-gold-600 disabled:opacity-50">
            {savingStyled ? "Saving…" : "Save styled text"}
          </button>
        </div>
      )}

      {error && <p className="text-xs text-ribbon-600">{error}</p>}
    </div>
  );
}
