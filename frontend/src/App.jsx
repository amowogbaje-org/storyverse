import { lazy, Suspense } from "react";
import { Routes, Route } from "react-router-dom";
import Navbar from "./components/layout/Navbar";
import Footer from "./components/layout/Footer";
import MobileTabBar from "./components/layout/MobileTabBar";
import LoadingSpinner from "./components/common/LoadingSpinner";
import InstallPrompt from "./components/common/InstallPrompt";
import { usePageviewTracking } from "./hooks/usePageviewTracking";
import { usePushSetup } from "./hooks/usePushSetup";
import { useReferralCapture } from "./hooks/useReferralCapture";

// HomePage is the single most common entry point (direct visits, search
// results, shared links to "/") - kept as a normal eager import so the very
// first paint doesn't wait on an extra chunk request on top of the main
// bundle. Everything else is lazy: each becomes its own chunk, fetched only
// when someone actually navigates there, which keeps that first load small
// for the common case instead of shipping the admin panel (charts and all)
// to every single reader up front.
import HomePage from "./pages/HomePage";

const BrowsePage = lazy(() => import("./pages/BrowsePage"));
const StoryDetailPage = lazy(() => import("./pages/StoryDetailPage"));
const EpisodeReaderPage = lazy(() => import("./pages/EpisodeReaderPage"));
const SearchPage = lazy(() => import("./pages/SearchPage"));
const AuthorPage = lazy(() => import("./pages/AuthorPage"));
const LoginPage = lazy(() => import("./pages/LoginPage"));
const ForgotPasswordPage = lazy(() => import("./pages/ForgotPasswordPage"));
const RegisterPage = lazy(() => import("./pages/RegisterPage"));
const ProfilePage = lazy(() => import("./pages/ProfilePage"));
const LibraryPage = lazy(() => import("./pages/LibraryPage"));
const BadgesPage = lazy(() => import("./pages/BadgesPage"));
const ReferralsPage = lazy(() => import("./pages/ReferralsPage"));
const SupportPage = lazy(() => import("./pages/SupportPage"));
const PrivacyPolicyPage = lazy(() => import("./pages/PrivacyPolicyPage"));
const TermsPage = lazy(() => import("./pages/TermsPage"));
const NotFoundPage = lazy(() => import("./pages/NotFoundPage"));

const AdminLayout = lazy(() => import("./pages/admin/AdminLayout"));
const AdminDashboardPage = lazy(() => import("./pages/admin/AdminDashboardPage"));
const AdminStoriesPage = lazy(() => import("./pages/admin/AdminStoriesPage"));
const AdminStoryImportPage = lazy(() => import("./pages/admin/AdminStoryImportPage"));
const AdminStoryEditorPage = lazy(() => import("./pages/admin/AdminStoryEditorPage"));
const AdminPenNamesPage = lazy(() => import("./pages/admin/AdminPenNamesPage"));
const AdminEarningsPage = lazy(() => import("./pages/admin/AdminEarningsPage"));
const AdminAnalyticsPage = lazy(() => import("./pages/admin/AdminAnalyticsPage"));
const AdminEmailBlacklistPage = lazy(() => import("./pages/admin/AdminEmailBlacklistPage"));
const AdminUsersPage = lazy(() => import("./pages/admin/AdminUsersPage"));
const AdminPayoutsPage = lazy(() => import("./pages/admin/AdminPayoutsPage"));

export default function App() {
  usePageviewTracking();
  usePushSetup();
  useReferralCapture();

  return (
    <div className="flex min-h-screen flex-col">
      <Navbar />
      <main className="flex-1 pb-16 md:pb-0">
        <Suspense fallback={<LoadingSpinner />}>
          <Routes>
            <Route path="/" element={<HomePage />} />
            <Route path="/browse" element={<BrowsePage />} />
            <Route path="/search" element={<SearchPage />} />
            <Route path="/stories/:slug" element={<StoryDetailPage />} />
            <Route path="/stories/:slug/episodes/:episodeNumber" element={<EpisodeReaderPage />} />
            <Route path="/authors/:slug" element={<AuthorPage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/forgot-password" element={<ForgotPasswordPage />} />
            <Route path="/register" element={<RegisterPage />} />
            <Route path="/profile" element={<ProfilePage />} />
            <Route path="/library" element={<LibraryPage />} />
            <Route path="/badges" element={<BadgesPage />} />
            <Route path="/referrals" element={<ReferralsPage />} />
            <Route path="/support" element={<SupportPage />} />
            <Route path="/privacy" element={<PrivacyPolicyPage />} />
            <Route path="/terms" element={<TermsPage />} />

            <Route path="/admin" element={<AdminLayout />}>
              <Route index element={<AdminDashboardPage />} />
              <Route path="stories" element={<AdminStoriesPage />} />
              <Route path="stories/import" element={<AdminStoryImportPage />} />
              <Route path="stories/:id" element={<AdminStoryEditorPage />} />
              <Route path="pen-names" element={<AdminPenNamesPage />} />
              <Route path="earnings" element={<AdminEarningsPage />} />
              <Route path="analytics" element={<AdminAnalyticsPage />} />
              <Route path="email-blacklist" element={<AdminEmailBlacklistPage />} />
              <Route path="payouts" element={<AdminPayoutsPage />} />
              <Route path="users" element={<AdminUsersPage />} />
            </Route>

            <Route path="*" element={<NotFoundPage />} />
          </Routes>
        </Suspense>
      </main>
      <Footer />
      <InstallPrompt />
      <MobileTabBar />
    </div>
  );
}
