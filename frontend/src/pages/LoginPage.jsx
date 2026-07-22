import { useState } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import Container from "../components/common/Container";

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await login(email, password);
      navigate(params.get("next") || "/");
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't sign you in. Check your details and try again.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <Container className="max-w-sm py-14">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Welcome back</h1>
      <p className="mt-1 text-sm text-ink-500">Sign in to keep reading, liking, and bookmarking.</p>

      <form onSubmit={submit} className="mt-6 space-y-3">
        <input
          type="email" required value={email} onChange={(e) => setEmail(e.target.value)}
          placeholder="Email" className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
        />
        <input
          type="password" required value={password} onChange={(e) => setPassword(e.target.value)}
          placeholder="Password" className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
        />
        {error && <p className="text-sm text-ribbon-600">{error}</p>}
        <button
          type="submit" disabled={loading}
          className="w-full rounded-card bg-ink-950 py-2.5 text-sm font-medium text-parchment-50 disabled:opacity-50"
        >
          {loading ? "Signing in…" : "Sign in"}
        </button>
      </form>

      <p className="mt-5 text-center text-sm text-ink-500">
        New here? <Link to="/register" className="text-teal-700 hover:underline">Create an account</Link>
      </p>
    </Container>
  );
}
