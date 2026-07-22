import { useAllBadges, useMyBadges } from "../hooks/queries/useBadges";
import { useAuth } from "../context/AuthContext";
import BadgeGrid from "../components/badges/BadgeGrid";
import LoadingSpinner from "../components/common/LoadingSpinner";
import Container from "../components/common/Container";

export default function BadgesPage() {
  const { isAuthenticated } = useAuth();
  const { data: allData, isLoading } = useAllBadges();
  const { data: mineData } = useMyBadges(isAuthenticated);

  const badges = allData?.data ?? [];
  const earnedIds = (mineData?.data ?? []).map((b) => b.id);

  return (
    <Container className="py-6 pb-24">
      <h1 className="font-display text-2xl font-semibold text-ink-950">Badges</h1>
      <p className="mt-1 text-sm text-ink-500">
        {isAuthenticated
          ? `You've earned ${earnedIds.length} of ${badges.length} badges. Keep reading to unlock more.`
          : "Sign in to start earning badges as you read."}
      </p>
      <div className="mt-6">
        {isLoading ? <LoadingSpinner /> : <BadgeGrid badges={badges} earnedIds={earnedIds} />}
      </div>
    </Container>
  );
}
