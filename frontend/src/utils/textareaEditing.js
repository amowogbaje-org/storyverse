/**
 * Wraps the current selection in `before`/`after` (e.g. ** **, for bold).
 * If nothing is selected, inserts `placeholder` between them instead and
 * selects it, so the toolbar button is useful even with an empty selection.
 * Returns the new full text value; the caller is responsible for restoring
 * focus/selection on the textarea element afterward (see useToolbarAction).
 */
export function wrapSelection(value, start, end, before, after, placeholder) {
  const selected = value.slice(start, end) || placeholder;
  const newValue = value.slice(0, start) + before + selected + after + value.slice(end);

  return {
    value: newValue,
    selectionStart: start + before.length,
    selectionEnd: start + before.length + selected.length,
  };
}

/**
 * Inserts a standalone block (e.g. a *** scene break) on its own line,
 * padded with blank lines so it doesn't run into surrounding paragraphs -
 * RichText.jsx only recognizes a scene break on a line by itself.
 */
export function insertBlock(value, start, end, block) {
  const before = value.slice(0, start);
  const after = value.slice(end);
  const needsLeadingBreak = before.length > 0 && !before.endsWith("\n\n");
  const needsTrailingBreak = after.length > 0 && !after.startsWith("\n\n");
  const insert = `${needsLeadingBreak ? "\n\n" : ""}${block}${needsTrailingBreak ? "\n\n" : ""}`;
  const newValue = before + insert + after;

  return {
    value: newValue,
    selectionStart: before.length + insert.length,
    selectionEnd: before.length + insert.length,
  };
}
