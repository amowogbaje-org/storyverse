import { Routes, Route } from "react-router-dom";
import Navbar from "./components/layout/Navbar";
import Footer from "./components/layout/Footer";
import MobileTabBar from "./components/layout/MobileTabBar";
import { usePageviewTracking } from "./hooks/usePageviewTracking";

import HomePage from "./pages/HomePage";
import BrowsePage from "./pages/BrowsePage";
import StoryDetailPage from "./pages/StoryDetailPage";
import EpisodeReaderPage from "./pages/EpisodeReaderPage";
import SearchPage from "./pages/SearchPage";
import AuthorPage from "./pages/AuthorPage";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";
import ProfilePage from "./pages/ProfilePage";
import LibraryPage from "./pages/LibraryPage";
import BadgesPage from "./pages/BadgesPage";
import SubscriptionPage from "./pages/SubscriptionPage";
import SupportPage from "./pages/SupportPage";
import NotFoundPage from "./pages/NotFoundPage";
import AdminLayout from "./pages/admin/AdminLayout";
import AdminDashboardPage from "./pages/admin/AdminDashboardPage";
import AdminStoriesPage from "./pages/admin/AdminStoriesPage";
import AdminStoryEditorPage from "./pages/admin/AdminStoryEditorPage";
import AdminPenNamesPage from "./pages/admin/AdminPenNamesPage";
import AdminEarningsPage from "./pages/admin/AdminEarningsPage";
import AdminAnalyticsPage from "./pages/admin/AdminAnalyticsPage";

export default function App() {
  usePageviewTracking();

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
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/profile" element={<ProfilePage />} />
          <Route path="/library" element={<LibraryPage />} />
          <Route path="/badges" element={<BadgesPage />} />
          <Route path="/subscription" element={<SubscriptionPage />} />
          <Route path="/support" element={<SupportPage />} />

          <Route path="/admin" element={<AdminLayout />}>
            <Route index element={<AdminDashboardPage />} />
            <Route path="stories" element={<AdminStoriesPage />} />
            <Route path="stories/:id" element={<AdminStoryEditorPage />} />
            <Route path="pen-names" element={<AdminPenNamesPage />} />
            <Route path="earnings" element={<AdminEarningsPage />} />
            <Route path="analytics" element={<AdminAnalyticsPage />} />
          </Route>

          <Route path="*" element={<NotFoundPage />} />
        </Routes>
      </main>
      <Footer />
      <MobileTabBar />
    </div>
  );
}
