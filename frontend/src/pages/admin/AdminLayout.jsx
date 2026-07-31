import { NavLink, Navigate, Outlet } from "react-router-dom";
import { useAuth } from "../../context/AuthContext";
import Container from "../../components/common/Container";

const LINKS = [
  { to: "/admin", label: "Dashboard", end: true },
  { to: "/admin/stories", label: "Stories" },
  { to: "/admin/pen-names", label: "Pen names" },
  { to: "/admin/earnings", label: "Earnings" },
];

export default function AdminLayout() {
  const { user, loading, isAuthenticated } = useAuth();

  if (loading) return null;
  if (!isAuthenticated) return <Navigate to="/login?next=/admin" replace />;
  if (!["author", "admin"].includes(user?.role)) return <Navigate to="/" replace />;

  const links = user.role === "admin"
    ? [...LINKS, { to: "/admin/analytics", label: "Platform analytics" }, { to: "/admin/email-blacklist", label: "Email blacklist" }, { to: "/admin/payouts", label: "Payouts" }]
    : LINKS;

  return (
    <Container className="py-6 pb-24">
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="font-mono text-[11px] uppercase tracking-widest text-gold-600">
            {user.role === "admin" ? "Platform admin" : "Author studio"}
          </p>
          <h1 className="font-display text-2xl font-semibold text-ink-950">Storyverse Studio</h1>
        </div>
        <nav className="flex flex-wrap gap-1 rounded-full border border-ink-950/10 bg-white/60 p-1">
          {links.map((l) => (
            <NavLink
              key={l.to}
              to={l.to}
              end={l.end}
              className={({ isActive }) =>
                `rounded-full px-3 py-1.5 text-sm font-medium ${
                  isActive ? "bg-ink-950 text-parchment-50" : "text-ink-600 hover:text-ink-950"
                }`
              }
            >
              {l.label}
            </NavLink>
          ))}
        </nav>
      </div>
      <Outlet />
    </Container>
  );
}
