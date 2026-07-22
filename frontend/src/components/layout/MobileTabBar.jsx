import { NavLink } from "react-router-dom";

const TABS = [
  { to: "/", label: "Home", icon: "home" },
  { to: "/browse", label: "Browse", icon: "grid" },
  { to: "/search", label: "Search", icon: "search" },
  { to: "/library", label: "Library", icon: "bookmark" },
  { to: "/profile", label: "Profile", icon: "user" },
];

const ICONS = {
  home: "M3 11.5 12 4l9 7.5M5 10v9h5v-5h4v5h5v-9",
  grid: "M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z",
  search: "M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14ZM21 21l-4.3-4.3",
  bookmark: "M6 3h12v18l-6-4-6 4Z",
  user: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21c1.5-4 5-6 8-6s6.5 2 8 6",
};

export default function MobileTabBar() {
  return (
    <nav className="fixed inset-x-0 bottom-0 z-40 border-t border-ink-950/10 bg-parchment-50/95 backdrop-blur md:hidden">
      <ul className="mx-auto flex max-w-6xl justify-between px-2">
        {TABS.map((tab) => (
          <li key={tab.to} className="flex-1">
            <NavLink
              to={tab.to}
              end={tab.to === "/"}
              className={({ isActive }) =>
                `flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium ${
                  isActive ? "text-gold-600" : "text-ink-500"
                }`
              }
            >
              {({ isActive }) => (
                <>
                  <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8">
                    <path d={ICONS[tab.icon]} strokeLinecap="round" strokeLinejoin="round" />
                  </svg>
                  {tab.label}
                  <span className={`mt-0.5 h-0.5 w-4 rounded-full ${isActive ? "bg-gold-500" : "bg-transparent"}`} />
                </>
              )}
            </NavLink>
          </li>
        ))}
      </ul>
    </nav>
  );
}
