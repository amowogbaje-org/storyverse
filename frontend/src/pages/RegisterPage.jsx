import { useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import Container from "../components/common/Container";

export default function RegisterPage() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: "", email: "", password: "" });
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

  function update(key, value) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function submit(e) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await register(form);
      navigate("/");
    } catch (err) {
      setError(err.response?.data?.message || "Couldn't create your account. Please check your details.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <Container className="max-w-sm py-14">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Join Storyverse</h1>
      <p className="mt-1 text-sm text-ink-500">
        Free — unlocks more free episodes on every story, likes, bookmarks, and comments.
      </p>

      <form onSubmit={submit} className="mt-6 space-y-3">
        <input
          required value={form.name} onChange={(e) => update("name", e.target.value)}
          placeholder="Display name" className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
        />
        <input
          type="email" required value={form.email} onChange={(e) => update("email", e.target.value)}
          placeholder="Email" className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
        />
        <input
          type="password" required value={form.password} onChange={(e) => update("password", e.target.value)}
          placeholder="Password" className="w-full rounded-card border border-ink-950/15 bg-white/70 px-4 py-2.5 text-sm outline-none focus:border-gold-500"
        />
        {error && <p className="text-sm text-ribbon-600">{error}</p>}
        <button
          type="submit" disabled={loading}
          className="w-full rounded-card bg-ink-950 py-2.5 text-sm font-medium text-parchment-50 disabled:opacity-50"
        >
          {loading ? "Creating account…" : "Create account"}
        </button>
      </form>

      <p className="mt-5 text-center text-sm text-ink-500">
        Already have an account? <Link to="/login" className="text-teal-700 hover:underline">Sign in</Link>
      </p>
    </Container>
  );
}
