import Container from "../components/common/Container";

const SECTIONS = [
  {
    title: "1. What this policy covers",
    body: [
      "This Privacy Policy explains what information Storyverse (\"we\", \"us\") collects when you use the Storyverse website and apps, how we use it, and the choices you have. By creating an account or otherwise using Storyverse, you agree to the practices described here.",
    ],
  },
  {
    title: "2. Information we collect",
    body: [
      "Account information: your name, email address, and password (stored as a salted hash — we never store your plain-text password). If you sign up or sign in with Google, we receive your name, email address, and profile photo from Google instead of a password.",
      "Reading activity: which stories and episodes you view, your reading progress, likes, bookmarks, comments, and badges/rewards earned.",
      "Payment information: if you subscribe to premium access, our payment partners (Stripe, Paystack, or Flutterwave, depending on your region) process your card or mobile-money details directly. We receive only a confirmation of payment, your plan, and the currency/amount — we do not store your full card number.",
      "Device and usage information: IP address, browser type, approximate location (used to determine your local currency and pricing), pages visited, and session identifiers used for analytics.",
    ],
  },
  {
    title: "3. Google Sign-In",
    body: [
      "If you choose \"Sign in with Google\", Google authenticates you and shares your name, email address, and profile photo with Storyverse via Google Identity Services. We use this only to create or sign you into your Storyverse account. We do not post to your Google account, read your Gmail, or access your Google Drive or contacts.",
      "You can remove Storyverse's access to your Google account at any time from your Google Account's \"Third-party apps & services\" settings.",
    ],
  },
  {
    title: "4. How we use your information",
    body: [
      "To create and secure your account, including sending one-time passcodes (OTP) to verify your email address.",
      "To operate core features: tracking reading progress, unlocking free/premium episodes, likes, bookmarks, comments, and the badge/reward system.",
      "To process subscription payments and determine regional pricing based on your detected country.",
      "To send transactional emails (verification codes, receipts, important account notices) and, where you've opted in, product updates.",
      "To measure engagement (reads, visits, sign-ups) so we can improve the product — this is done in aggregate wherever possible.",
    ],
  },
  {
    title: "5. Sharing your information",
    body: [
      "We share information with the service providers that help us run Storyverse: payment processors (Stripe, Paystack, Flutterwave) for billing, our email delivery provider for OTPs and receipts, and infrastructure/hosting providers.",
      "We do not sell your personal information to advertisers or data brokers.",
      "We may disclose information if required by law, or to protect the rights, safety, and security of Storyverse, our authors, or our readers.",
    ],
  },
  {
    title: "6. Cookies and similar technologies",
    body: [
      "We use a session identifier (stored in your browser) to keep you signed in and to attribute anonymous reads/visits for analytics, even before you create an account. You can clear this by clearing your browser storage, though some features (like guest reading limits) rely on it.",
    ],
  },
  {
    title: "7. Data retention",
    body: [
      "We retain account information for as long as your account is active. If you delete your account, we remove or anonymize your personal information within a reasonable period, except where we're required to retain records (for example, payment records for tax/accounting purposes).",
    ],
  },
  {
    title: "8. Your choices",
    body: [
      "You can review and update your profile information from your account settings at any time.",
      "You can request a copy of your data, or request deletion of your account, by contacting us at the email below.",
      "You can unlink Google Sign-In and set a password instead from your account settings, or vice versa.",
    ],
  },
  {
    title: "9. Children's privacy",
    body: [
      "Storyverse is not directed at children under 13, and we do not knowingly collect personal information from children under 13. If you believe a child has created an account, contact us and we'll take appropriate action.",
    ],
  },
  {
    title: "10. Changes to this policy",
    body: [
      "We may update this policy from time to time. If we make material changes, we'll let you know via the app or by email before the changes take effect.",
    ],
  },
  {
    title: "11. Contact us",
    body: [
      "Questions about this policy or your data? Reach us at privacy@storyverse.app.",
    ],
  },
];

export default function PrivacyPolicyPage() {
  return (
    <Container className="max-w-2xl py-10 pb-24">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Privacy Policy</h1>
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
