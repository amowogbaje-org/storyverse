import { useAllBadges } from "../hooks/queries/useBadges";
import { useAuth } from "../context/AuthContext";
import BadgeGrid from "../components/badges/BadgeGrid";
import NextBadges from "../components/badges/NextBadges";
import LoadingSpinner from "../components/common/LoadingSpinner";
import Container from "../components/common/Container";

export default function BadgesPage() {
  const { isAuthenticated } = useAuth();
  const { data: allData, isLoading } = useAllBadges();

  const badges = allData?.data ?? [];
  // BadgeController::index() already returns an `earned` boolean per badge
  // computed from the same lookup useMyBadges() used to fetch separately -
  // deriving earnedIds from that removes a second full round trip for data
  // this call already had.
  const earnedIds = badges.filter((b) => b.earned).map((b) => b.id);

  return (
    <Container className="py-6 pb-24">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Badges</h1>
      <p className="mt-1 text-sm text-ink-500">
        {isAuthenticated
          ? `You've earned ${earnedIds.length} of ${badges.length} badges. Keep reading to unlock more.`
          : "Sign in to start earning badges as you read."}
      </p>
      <div className="mt-6">
        <NextBadges variant="full" />
        {isLoading ? <LoadingSpinner /> : <BadgeGrid badges={badges} earnedIds={earnedIds} />}
      </div>
    </Container>
  );
}
