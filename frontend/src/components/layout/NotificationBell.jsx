import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useNotifications, useUnreadNotificationCount } from "../../hooks/queries/useNotifications";
import { useMarkNotificationRead, useMarkAllNotificationsRead } from "../../hooks/mutations/useNotificationMutations";

function timeAgo(iso) {
  const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (seconds < 60) return "just now";
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  return `${Math.floor(hours / 24)}d ago`;
}

export default function NotificationBell() {
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  const navigate = useNavigate();

  const { data: countData } = useUnreadNotificationCount(true);
  const { data: listData } = useNotifications(open);
  const markRead = useMarkNotificationRead();
  const markAllRead = useMarkAllNotificationsRead();

  const unreadCount = countData?.data?.count ?? 0;
  const notifications = listData?.data ?? [];

  useEffect(() => {
    function onClickOutside(e) {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    }
    document.addEventListener("mousedown", onClickOutside);
    return () => document.removeEventListener("mousedown", onClickOutside);
  }, []);

  function openNotification(n) {
    if (!n.read) markRead.mutate(n.id);
    setOpen(false);
    if (n.url) navigate(n.url);
  }

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((v) => !v)}
        className="relative flex h-9 w-9 items-center justify-center rounded-full text-ink-600 hover:bg-ink-950/5 hover:text-ink-950"
        aria-label="Notifications"
      >
        <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8">
          <path d="M6 10a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 14 6 10Z" strokeLinecap="round" strokeLinejoin="round" />
          <path d="M10 19a2 2 0 0 0 4 0" strokeLinecap="round" />
        </svg>
        {unreadCount > 0 && (
          <span className="absolute -right-0.5 -top-0.5 grid h-4 min-w-[16px] place-items-center rounded-full bg-ribbon-500 px-1 text-[10px] font-semibold text-white">
            {unreadCount > 9 ? "9+" : unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-11 z-30 max-h-[70vh] w-80 overflow-y-auto rounded-card border border-ink-950/10 bg-white shadow-card">
          <div className="flex items-center justify-between border-b border-ink-950/8 px-4 py-2.5">
            <p className="text-sm font-semibold text-ink-950">Notifications</p>
            {unreadCount > 0 && (
              <button
                onClick={() => markAllRead.mutate()}
                className="text-xs font-medium text-teal-700 hover:underline"
              >
                Mark all read
              </button>
            )}
          </div>

          {notifications.length === 0 && (
            <p className="px-4 py-8 text-center text-sm text-ink-500">You're all caught up.</p>
          )}

          {notifications.map((n) => (
            <button
              key={n.id}
              onClick={() => openNotification(n)}
              className={`flex w-full items-start gap-2.5 border-b border-ink-950/5 px-4 py-3 text-left hover:bg-parchment-100 ${
                n.read ? "" : "bg-gold-400/5"
              }`}
            >
              {!n.read && <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-gold-500" />}
              <div className={`min-w-0 flex-1 ${n.read ? "pl-3.5" : ""}`}>
                <p className="truncate text-sm font-medium text-ink-950">{n.title}</p>
                <p className="mt-0.5 line-clamp-2 text-xs text-ink-500">{n.body}</p>
                <p className="mt-1 text-[11px] text-ink-400">{timeAgo(n.created_at)}</p>
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
