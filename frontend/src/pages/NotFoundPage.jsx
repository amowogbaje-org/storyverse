import { Link } from "react-router-dom";
import Container from "../components/common/Container";

export default function NotFoundPage() {
  return (
    <Container className="py-20 text-center">
      <p className="font-display text-4xl text-ink-950">404</p>
      <p className="mt-2 text-sm text-ink-500">This page doesn't exist.</p>
      <Link to="/" className="mt-5 inline-block rounded-full bg-ink-950 px-4 py-2 text-sm text-parchment-50">
        Back home
      </Link>
    </Container>
  );
}
