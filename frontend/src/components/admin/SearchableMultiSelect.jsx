import { useMemo, useState } from "react";

/**
 * @param {{id: number, name: string}[]} options
 * @param {number[]} selectedIds
 * @param {(ids: number[]) => void} onChange
 * @param {string} label
 * @param {string} [placeholder]
 * @param {string} [emptyHint] - shown when no options match the search
 */
export default function SearchableMultiSelect({ options, selectedIds, onChange, label, placeholder = "Search…", emptyHint = "No matches." }) {
  const [query, setQuery] = useState("");

  const selected = useMemo(() => options.filter((o) => selectedIds.includes(o.id)), [options, selectedIds]);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return options;
    return options.filter((o) => o.name.toLowerCase().includes(q));
  }, [options, query]);

  function toggle(id) {
    if (selectedIds.includes(id)) {
      onChange(selectedIds.filter((sid) => sid !== id));
    } else {
      onChange([...selectedIds, id]);
    }
  }

  return (
    <div>
      <label className="mb-1 block text-xs font-medium text-ink-500">{label}</label>

      {selected.length > 0 && (
        <div className="mb-1.5 flex flex-wrap gap-1.5">
          {selected.map((o) => (
            <button
              key={o.id}
              type="button"
              onClick={() => toggle(o.id)}
              className="flex items-center gap-1 rounded-full bg-ink-950 px-2.5 py-1 text-xs font-medium text-parchment-50"
            >
              {o.name}
              <span aria-hidden="true">×</span>
            </button>
          ))}
        </div>
      )}

      <input
        type="text"
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder={placeholder}
        className="w-full rounded-card border border-ink-950/15 bg-white px-3 py-2 text-sm"
      />

      <div className="mt-1.5 max-h-40 overflow-y-auto rounded-card border border-ink-950/10 bg-white">
        {filtered.length === 0 && <p className="px-3 py-2 text-xs text-ink-400">{emptyHint}</p>}
        {filtered.map((o) => {
          const isSelected = selectedIds.includes(o.id);
          return (
            <button
              key={o.id}
              type="button"
              onClick={() => toggle(o.id)}
              className={`flex w-full items-center justify-between px-3 py-1.5 text-left text-sm hover:bg-parchment-100 ${
                isSelected ? "text-ink-950" : "text-ink-700"
              }`}
            >
              {o.name}
              {isSelected && <span className="text-teal-700">✓</span>}
            </button>
          );
        })}
      </div>
    </div>
  );
}
