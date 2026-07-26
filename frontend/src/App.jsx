import { Routes, Route } from "react-router-dom";
import Navbar from "./components/layout/Navbar";
import Footer from "./components/layout/Footer";
import MobileTabBar from "./components/layout/MobileTabBar";
import { usePageviewTracking } from "./hooks/usePageviewTracking";
import { usePushSetup } from "./hooks/usePushSetup";
import { useReferralCapture } from "./hooks/useReferralCapture";

import HomePage from "./pages/HomePage";
import BrowsePage from "./pages/BrowsePage";
import StoryDetailPage from "./pages/StoryDetailPage";
import EpisodeReaderPage from "./pages/EpisodeReaderPage";
import SearchPage from "./pages/SearchPage";
import AuthorPage from "./pages/AuthorPage";
import LoginPage from "./pages/LoginPage";
import ForgotPasswordPage from "./pages/ForgotPasswordPage";
import RegisterPage from "./pages/RegisterPage";
import ProfilePage from "./pages/ProfilePage";
import LibraryPage from "./pages/LibraryPage";
import BadgesPage from "./pages/BadgesPage";
import ReferralsPage from "./pages/ReferralsPage";
import SubscriptionPage from "./pages/SubscriptionPage";
import SupportPage from "./pages/SupportPage";
import PrivacyPolicyPage from "./pages/PrivacyPolicyPage";
import TermsPage from "./pages/TermsPage";
import NotFoundPage from "./pages/NotFoundPage";
import AdminLayout from "./pages/admin/AdminLayout";
import AdminDashboardPage from "./pages/admin/AdminDashboardPage";
import AdminStoriesPage from "./pages/admin/AdminStoriesPage";
import AdminStoryEditorPage from "./pages/admin/AdminStoryEditorPage";
import AdminPenNamesPage from "./pages/admin/AdminPenNamesPage";
import AdminEarningsPage from "./pages/admin/AdminEarningsPage";
import AdminAnalyticsPage from "./pages/admin/AdminAnalyticsPage";
import AdminEmailBlacklistPage from "./pages/admin/AdminEmailBlacklistPage";

export default function App() {
  usePageviewTracking();
  usePushSetup();
  useReferralCapture();

  return (
    <div className="flex min-h-screen flex-col">
      <Navbar />
      <main className="flex-1 pb-16 md:pb-0">
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
          <Route path="/subscription" element={<SubscriptionPage />} />
          <Route path="/support" element={<SupportPage />} />
          <Route path="/privacy" element={<PrivacyPolicyPage />} />
          <Route path="/terms" element={<TermsPage />} />

          <Route path="/admin" element={<AdminLayout />}>
            <Route index element={<AdminDashboardPage />} />
            <Route path="stories" element={<AdminStoriesPage />} />
            <Route path="stories/:id" element={<AdminStoryEditorPage />} />
            <Route path="pen-names" element={<AdminPenNamesPage />} />
            <Route path="earnings" element={<AdminEarningsPage />} />
            <Route path="analytics" element={<AdminAnalyticsPage />} />
            <Route path="email-blacklist" element={<AdminEmailBlacklistPage />} />
          </Route>

          <Route path="*" element={<NotFoundPage />} />
        </Routes>
      </main>
      <Footer />
      <MobileTabBar />
    </div>
  );
}
