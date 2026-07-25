import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import Container from "../common/Container";
import NotificationBell from "./NotificationBell";

export default function Navbar() {
  const { user, isAuthenticated, loading, logout } = useAuth();
  const [menuOpen, setMenuOpen] = useState(false);
  const [q, setQ] = useState("");
  const navigate = useNavigate();

  function submitSearch(e) {
    e.preventDefault();
    if (q.trim()) navigate(`/search?q=${encodeURIComponent(q.trim())}`);
  }

  return (
    <header className="sticky top-0 z-40 border-b border-ink-950/10 bg-parchment-50/95 backdrop-blur">
      <Container className="flex h-16 items-center gap-4">
        <Link to="/" className="shrink-0 font-display text-xl font-semibold tracking-tight text-ink-950">
          Storyverse
        </Link>

        <nav className="hidden items-center gap-5 text-sm font-medium text-ink-700 md:flex">
          <Link to="/browse" className="hover:text-ink-950">Browse</Link>
          <Link to="/browse?sort=new" className="hover:text-ink-950">New releases</Link>
          <Link to="/browse?sort=popular" className="hover:text-ink-950">Popular</Link>
        </nav>

        <form onSubmit={submitSearch} className="ml-auto hidden flex-1 max-w-sm items-center sm:flex">
          <input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            type="search"
            placeholder="Search stories or authors…"
            className="w-full rounded-full border border-ink-950/15 bg-white/70 px-4 py-1.5 text-sm outline-none placeholder:text-ink-300 focus:border-gold-500"
          />
        </form>

        <div className="ml-auto flex items-center gap-2 sm:ml-0">
          {isAuthenticated && !loading && <NotificationBell />}
          <div className="relative">
          {loading ? (
            <div className="h-8 w-8 animate-pulse rounded-full bg-ink-950/10 sm:w-24" aria-hidden="true" />
          ) : isAuthenticated ? (
            <button
              onClick={() => setMenuOpen((v) => !v)}
              className="flex items-center gap-2 rounded-full border border-ink-950/10 bg-white/60 py-1 pl-1 pr-3"
            >
              <span className="grid h-7 w-7 place-items-center rounded-full bg-teal-700 text-xs font-semibold text-parchment-50">
                {user?.display_name?.[0]?.toUpperCase() ?? "U"}
              </span>
              <span className="hidden text-sm font-medium sm:inline">{user?.display_name ?? "Account"}</span>
            </button>
          ) : (
            <Link
              to="/login"
              className="rounded-full bg-ink-950 px-4 py-1.5 text-sm font-medium text-parchment-50 hover:bg-ink-900"
            >
              Sign in
            </Link>
          )}

          {menuOpen && isAuthenticated && (
            <div
              className="absolute right-0 top-11 w-52 overflow-hidden rounded-card border border-ink-950/10 bg-white shadow-card"
              onMouseLeave={() => setMenuOpen(false)}
            >
              <Link to="/library" className="block px-4 py-2.5 text-sm hover:bg-parchment-100" onClick={() => setMenuOpen(false)}>My library</Link>
              <Link to="/badges" className="block px-4 py-2.5 text-sm hover:bg-parchment-100" onClick={() => setMenuOpen(false)}>Badges</Link>
              {["author", "admin"].includes(user?.role) && (
                <Link to="/admin" className="block px-4 py-2.5 text-sm text-gold-600 hover:bg-parchment-100" onClick={() => setMenuOpen(false)}>Studio</Link>
              )}
              <Link to="/subscription" className="block px-4 py-2.5 text-sm hover:bg-parchment-100" onClick={() => setMenuOpen(false)}>Subscription</Link>
              <Link to="/profile" className="block px-4 py-2.5 text-sm hover:bg-parchment-100" onClick={() => setMenuOpen(false)}>Settings</Link>
              <Link to="/support" className="block px-4 py-2.5 text-sm hover:bg-parchment-100" onClick={() => setMenuOpen(false)}>Support</Link>
              <button
                onClick={() => { setMenuOpen(false); logout(); }}
                className="block w-full border-t border-ink-950/10 px-4 py-2.5 text-left text-sm text-ribbon-600 hover:bg-parchment-100"
              >
                Log out
              </button>
            </div>
          )}
          </div>
        </div>
      </Container>
    </header>
  );
}
