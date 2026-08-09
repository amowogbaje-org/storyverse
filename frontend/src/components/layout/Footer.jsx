import { Link } from "react-router-dom";
import Container from "../common/Container";

export default function Footer() {
  return (
    <footer className="mt-16 hidden border-t border-ink-950/10 py-8 md:block">
      <Container className="flex items-center justify-between text-sm text-ink-500">
        <span>© {new Date().getFullYear()} Storyverse</span>
        <div className="flex gap-4">
          <Link to="/support" className="hover:text-ink-900">Support</Link>
          <Link to="/terms" className="hover:text-ink-900">Terms</Link>
          <Link to="/privacy" className="hover:text-ink-900">Privacy</Link>
        </div>
      </Container>
    </footer>
  );
}
