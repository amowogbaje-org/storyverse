// Lightweight markdown-ish renderer for episode body text.
//
// Why not a markdown library or dangerouslySetInnerHTML: episode content
// comes from an AI styling agent (see the content-styling pipeline) writing
// into a text column, so it's still just a string over the wire. Rendering
// it as literal HTML would mean trusting model output as markup, which is
// an XSS foot-gun. Instead this walks the text and inline-formatting tokens
// itself and emits React elements directly - there's no HTML parsing step,
// so there's nothing to sanitize and nothing to inject.
//
// Supported inline styles: **bold**, *italic* / _italic_, ***bold italic***,
// ~~strikethrough~~, `inline code`. Supported block-level: blank-line
// paragraphs, "> " blockquotes, and a scene break on its own line written as
// "***", "* * *", or "---".

const SCENE_BREAK = /^(\*\s?\*\s?\*|-{3,})$/;

// Order matters: bold+italic before bold before italic, so "***x***" isn't
// swallowed by the plain "**" rule first.
const INLINE_RULES = [
  { re: /\*\*\*([^*]+)\*\*\*/, render: (m, key) => <strong key={key}><em>{m[1]}</em></strong> },
  { re: /\*\*([^*]+)\*\*/, render: (m, key) => <strong key={key}>{m[1]}</strong> },
  { re: /~~([^~]+)~~/, render: (m, key) => <del key={key}>{m[1]}</del> },
  { re: /`([^`]+)`/, render: (m, key) => <code key={key} className="rounded bg-ink-950/10 px-1 py-0.5 font-mono text-[0.9em]">{m[1]}</code> },
  { re: /\*([^*]+)\*/, render: (m, key) => <em key={key}>{m[1]}</em> },
  { re: /_([^_]+)_/, render: (m, key) => <em key={key}>{m[1]}</em> },
];

function renderInline(text) {
  const nodes = [];
  let remaining = text;
  let key = 0;

  while (remaining.length) {
    let earliest = null;
    for (const rule of INLINE_RULES) {
      const match = rule.re.exec(remaining);
      if (match && (earliest === null || match.index < earliest.match.index)) {
        earliest = { rule, match };
      }
    }

    if (!earliest) {
      nodes.push(remaining);
      break;
    }

    const { rule, match } = earliest;
    if (match.index > 0) nodes.push(remaining.slice(0, match.index));
    nodes.push(rule.render(match, `i${key++}`));
    remaining = remaining.slice(match.index + match[0].length);
  }

  return nodes;
}

export default function RichText({ text, className = "" }) {
  if (!text) return null;

  // Blank line(s) separate paragraphs; a lone "> " line becomes a
  // blockquote; a lone "***" / "---" line becomes a centered scene break.
  const blocks = text.split(/\n{2,}/);

  return (
    <div className={className}>
      {blocks.map((block, i) => {
        const trimmed = block.trim();

        if (SCENE_BREAK.test(trimmed)) {
          return (
            <div key={i} className="my-6 text-center tracking-[0.5em] text-ink-300" aria-hidden="true">
              ⁂
            </div>
          );
        }

        const lines = block.split("\n");
        const isQuote = lines.every((l) => l.startsWith("> ") || l.trim() === "");

        const paragraphLines = lines.map((line, li) => {
          const content = isQuote ? line.replace(/^>\s?/, "") : line;
          return (
            <span key={li}>
              {renderInline(content)}
              {li < lines.length - 1 && <br />}
            </span>
          );
        });

        if (isQuote) {
          return (
            <blockquote key={i} className="my-4 border-l-2 border-gold-500/60 pl-4 italic text-ink-700">
              {paragraphLines}
            </blockquote>
          );
        }

        return (
          <p key={i} className="mb-4 last:mb-0">
            {paragraphLines}
          </p>
        );
      })}
    </div>
  );
}
