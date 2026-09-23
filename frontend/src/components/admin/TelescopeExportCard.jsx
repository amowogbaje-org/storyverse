import { useState } from "react";
import { downloadTelescopeExport } from "../../hooks/queries/useAdmin";

const TYPES = [
  { value: "", label: "All types" },
  { value: "request", label: "Requests" },
  { value: "query", label: "Queries" },
  { value: "exception", label: "Exceptions" },
  { value: "job", label: "Jobs" },
  { value: "log", label: "Logs" },
  { value: "mail", label: "Mail" },
  { value: "command", label: "Commands" },
  { value: "schedule", label: "Scheduled tasks" },
];

const WINDOWS = [
  { value: 24, label: "Last 24 hours" },
  { value: 48, label: "Last 48 hours" },
  { value: 168, label: "Last 7 days" },
  { value: 720, label: "Last 30 days" },
];

export default function TelescopeExportCard() {
  const [type, setType] = useState("");
  const [hours, setHours] = useState(48);
  const [state, setState] = useState("idle"); // idle | downloading | error

  async function handleDownload() {
    setState("downloading");
    try {
      await downloadTelescopeExport({ type, hours });
      setState("idle");
    } catch {
      setState("error");
    }
  }

  return (
    <div className="rounded-card border border-ink-950/10 bg-white/60 p-4 dark:bg-parchment-100/60">
      <h3 className="font-display text-lg font-semibold text-ink-950">Telescope export</h3>
      <p className="mt-1 text-xs text-ink-500">
        Download captured requests, queries, exceptions and more as a CSV - no terminal or cPanel needed.
      </p>
      <div className="mt-3 flex flex-wrap items-center gap-2">
        <select
          value={type}
          onChange={(e) => setType(e.target.value)}
          className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs dark:bg-parchment-100/70"
        >
          {TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
        </select>
        <select
          value={hours}
          onChange={(e) => setHours(Number(e.target.value))}
          className="rounded-full border border-ink-950/15 bg-white/70 px-3 py-1.5 text-xs dark:bg-parchment-100/70"
        >
          {WINDOWS.map((w) => <option key={w.value} value={w.value}>{w.label}</option>)}
        </select>
        <button
          onClick={handleDownload}
          disabled={state === "downloading"}
          className="rounded-full bg-ink-950 px-4 py-1.5 text-xs font-medium text-parchment-50 hover:bg-ink-900 disabled:opacity-50"
        >
          {state === "downloading" ? "Preparing…" : "Download CSV"}
        </button>
      </div>
      {state === "error" && (
        <p className="mt-2 text-xs text-ribbon-600">
          Couldn't export - Telescope may not be installed yet (see MONITORING.md), or you may need to sign in again.
        </p>
      )}
    </div>
  );
}
