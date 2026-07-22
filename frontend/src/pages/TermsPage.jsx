import Container from "../components/common/Container";

const SECTIONS = [
  {
    title: "1. Agreement to terms",
    body: [
      "These Terms of Service (\"Terms\") govern your access to and use of Storyverse. By creating an account, signing in (including via Google Sign-In), or otherwise using Storyverse, you agree to these Terms. If you don't agree, please don't use Storyverse.",
    ],
  },
  {
    title: "2. Accounts",
    body: [
      "You must provide accurate information when creating an account. You're responsible for keeping your password confidential and for all activity under your account.",
      "You can create an account with an email and password (verified via a one-time passcode sent to your email) or by signing in with Google. If you sign in with Google, you authorize us to receive your name, email, and profile photo from Google to create and manage your Storyverse account.",
      "You must be at least 13 years old to create an account.",
    ],
  },
  {
    title: "3. Reading access and subscriptions",
    body: [
      "Guests (not signed in) can read the first 2 episodes of any story. Registered readers get access to the first 5 episodes of any story for free.",
      "Free stories are fully accessible to registered readers at no cost. Premium stories require an active subscription for access beyond the first 5 episodes.",
      "If you've already read part of a story before a subscription requirement was introduced, that access is preserved — a new subscription requirement will never take away access you already have.",
      "Subscription pricing varies by region/currency to reflect local purchasing power, based on your detected or selected country. Once you subscribe, your price is locked in for that billing cycle even if your country setting changes later.",
      "Payments are processed by Stripe, Paystack, or Flutterwave depending on your region. Subscriptions renew automatically until cancelled; you can cancel any time from your account settings, effective at the end of the current billing period.",
    ],
  },
  {
    title: "4. Author content",
    body: [
      "Authors retain ownership of the stories they publish on Storyverse, and are responsible for ensuring they have the rights to publish that content.",
      "By publishing a story, an author grants Storyverse a license to host, display, and distribute that story to readers through the platform, consistent with the access rules described above.",
      "Authors are responsible for the accuracy of their earnings dashboards' underlying content and for complying with applicable law regarding the content they publish.",
    ],
  },
  {
    title: "5. Badges and rewards",
    body: [
      "Storyverse awards badges and rewards (such as bonus access or recommendations) based on reading activity, interactions, and subscription status. Badges and rewards have no cash value and may be changed, added, or removed at our discretion.",
    ],
  },
  {
    title: "6. Acceptable use",
    body: [
      "You agree not to: circumvent access controls or paywalls, scrape or bulk-download content, impersonate another person, harass other users in comments, or upload content that infringes someone else's rights or violates applicable law.",
      "We may suspend or terminate accounts that violate these Terms.",
    ],
  },
  {
    title: "7. Disclaimers",
    body: [
      "Storyverse is provided \"as is\". We don't guarantee that the service will be uninterrupted or error-free. Stories and views expressed within them belong to their respective authors and don't necessarily reflect Storyverse's views.",
    ],
  },
  {
    title: "8. Changes to these terms",
    body: [
      "We may update these Terms from time to time. If we make material changes, we'll notify you via the app or by email before the changes take effect. Continuing to use Storyverse after changes take effect means you accept the updated Terms.",
    ],
  },
  {
    title: "9. Contact us",
    body: [
      "Questions about these Terms? Reach us at support@storyverse.app.",
    ],
  },
];

export default function TermsPage() {
  return (
    <Container className="max-w-2xl py-10 pb-24">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Terms of Service</h1>
      <p className="mt-1 text-sm text-ink-500">Last updated: July 2026</p>

      <div className="mt-8 space-y-6">
        {SECTIONS.map((s) => (
          <section key={s.title}>
            <h2 className="font-display text-lg font-semibold text-ink-950">{s.title}</h2>
            <div className="mt-2 space-y-2">
              {s.body.map((p, i) => (
                <p key={i} className="text-sm leading-relaxed text-ink-600">{p}</p>
              ))}
            </div>
          </section>
        ))}
      </div>
    </Container>
  );
}
