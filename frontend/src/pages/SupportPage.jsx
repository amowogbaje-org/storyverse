import Container from "../components/common/Container";

const FAQS = [
  { q: "How many episodes can I read for free?", a: "Guests can read the first 2 episodes of any story. Register for a free account and that opens up to 5. Free stories are fully unlocked for registered readers; premium stories need a subscription past episode 5." },
  { q: "Does a subscription remove access I already have?", a: "No — if you've already read part of a story, a subscription only adds access, it never takes anything away." },
  { q: "What payment methods are supported?", a: "Stripe, Paystack, and Flutterwave, depending on your region." },
  { q: "How do badges work?", a: "Badges are awarded automatically as you read, interact, and subscribe. Check the Badges page to see what you've earned and what's next." },
];

export default function SupportPage() {
  return (
    <Container className="max-w-xl py-10 pb-24">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Support</h1>
      <p className="mt-1 text-sm text-ink-500">Answers to the most common questions. Can't find what you need? Reach us at support@storyverse.app.</p>

      <div className="mt-6 space-y-4">
        {FAQS.map((f) => (
          <details key={f.q} className="rounded-card border border-ink-950/10 bg-white/50 p-4">
            <summary className="cursor-pointer text-sm font-medium text-ink-950">{f.q}</summary>
            <p className="mt-2 text-sm text-ink-600">{f.a}</p>
          </details>
        ))}
      </div>
    </Container>
  );
}
