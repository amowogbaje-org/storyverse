<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BadgeController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\EpisodeController;
use App\Http\Controllers\Api\Integrations\CraftProfessorExportController;
use App\Http\Controllers\Api\InteractionController;
use App\Http\Controllers\Api\PenNameController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Outside the /v1 group deliberately - this is a fixed external contract
// (storyverse-api-docs.md, supplied by CraftProfessor) at exactly
// /api/stories/{slug}/json, no version segment. Public, no auth, matching
// the visibility of the public story page itself.
Route::get('/stories/{slug}/json', [CraftProfessorExportController::class, 'show']);

Route::prefix('v1')->group(function () {

    // Auth - all public
    Route::get('/', function () {
        return response()->json(['message' => 'Storyverse API v1']);
    });
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/otp/request', [AuthController::class, 'requestOtp']);
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('/auth/password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/password/reset', [AuthController::class, 'resetPassword']);
    Route::post('/auth/google', [AuthController::class, 'googleAuth']);
    // Deliberately public/unauthenticated: the whole point of this endpoint is
    // to get a new access token once the old one has already expired, so it
    // can't require a valid access token to call.
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Public - the same list StoryManagementController validates story prices
    // against and StoryPurchaseController resolves reader prices from (see
    // config/currencies.php) - the price-per-currency form and the currency
    // picker in Settings both read this instead of hardcoding the list twice.
    Route::get('/currencies', function () {
        return response()->json([
            'data' => collect(config('currencies'))
                ->map(fn ($c, $code) => ['code' => $code, ...$c])
                ->values(),
        ]);
    });

    // Auth - required
    Route::middleware('jwt.auth')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me', [AuthController::class, 'updateMe']);
        Route::patch('/me/notification-preferences', [AuthController::class, 'updateNotificationPreferences']);
        Route::patch('/me/reading-preferences', [AuthController::class, 'updateReadingPreferences']);
        Route::patch('/me/password', [AuthController::class, 'updatePassword']);
        Route::post('/me/become-author', [AuthController::class, 'becomeAuthor']);
        Route::patch('/me/country', [AuthController::class, 'updateCountry']);

        Route::post('/stories/{slug}/like', [InteractionController::class, 'like']);
        Route::delete('/stories/{slug}/like', [InteractionController::class, 'unlike']);
        Route::post('/stories/{slug}/bookmark', [InteractionController::class, 'bookmark']);
        Route::delete('/stories/{slug}/bookmark', [InteractionController::class, 'unbookmark']);
        Route::get('/me/bookmarks', [InteractionController::class, 'myBookmarks']);
        Route::get('/me/library', [InteractionController::class, 'myLibrary']);
        Route::post('/stories/{slug}/episodes/{episodeNumber}/progress', [InteractionController::class, 'updateProgress']);
        Route::get('/stories/{slug}/progress', [InteractionController::class, 'storyProgress']);

        Route::post('/stories/{slug}/comments', [CommentController::class, 'store']);
        Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

        Route::get('/me/badges', [BadgeController::class, 'mine']);
        Route::get('/me/badges/next', [BadgeController::class, 'next']);
        Route::get('/me/referrals', [\App\Http\Controllers\Api\ReferralController::class, 'mine']);
        Route::get('/me/payouts', [\App\Http\Controllers\Api\PayoutController::class, 'mine']);
        Route::patch('/me/payout-account', [\App\Http\Controllers\Api\PayoutController::class, 'updateAccount']);

        Route::post('/authors/{slug}/tip', [\App\Http\Controllers\Api\TipController::class, 'checkout']);
        Route::post('/stories/{slug}/purchase', [\App\Http\Controllers\Api\StoryPurchaseController::class, 'checkout']);
        Route::get('/stories/{slug}/purchase-status', [\App\Http\Controllers\Api\StoryPurchaseController::class, 'status']);

        Route::get('/me/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('/me/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::post('/me/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markRead']);
        Route::post('/me/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);

        Route::post('/me/push-subscriptions', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'store']);
        Route::delete('/me/push-subscriptions', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'destroy']);
        Route::post('/me/push-subscriptions/test', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'test']);

        // Author-or-admin (ownership checked in the controller) - not admin-only,
        // since an author should see where their own readers drop off without
        // needing platform-admin access.
        Route::get('/stories/{slug}/analytics/dropoff', [\App\Http\Controllers\Api\AnalyticsController::class, 'episodeDropoff']);
    });

    // Catalog / discovery - optional auth (locked flags / is_liked_by_me depend on auth state,
    // but guests must still get a 200, never a 401)
    Route::middleware('jwt.optional')->group(function () {
        Route::get('/categories', function () {
            // Only categories actually used by a published story - an empty
            // or single-story category isn't worth a filter chip and just
            // clutters the picker.
            return response()->json([
                'data' => \App\Models\Category::whereHas('stories', function ($q) {
                    $q->where('status', 'published');
                })->get(),
            ]);
        });
        Route::get('/genres', function () {
            return response()->json([
                'data' => \App\Models\Genre::whereHas('stories', function ($q) {
                    $q->where('status', 'published');
                })->get(),
            ]);
        });

        Route::get('/stories', [StoryController::class, 'index']);
        Route::get('/stories/new-releases', [StoryController::class, 'newReleases']);
        Route::get('/stories/popular', [StoryController::class, 'popular']);
        Route::get('/stories/{slug}', [StoryController::class, 'show']);
        Route::get('/stories/{slug}/episodes/{episodeNumber}', [EpisodeController::class, 'show']);

        Route::get('/authors/{slug}', [PenNameController::class, 'show']);
        Route::get('/authors/{slug}/stories', [PenNameController::class, 'stories']);

        Route::get('/search', [SearchController::class, 'native']);
        Route::post('/search/ai', [SearchController::class, 'ai']);

        Route::get('/stories/{slug}/comments', [CommentController::class, 'index']);

        Route::post('/stories/{slug}/share', [InteractionController::class, 'share']);

        Route::get('/badges', [BadgeController::class, 'index']);

        Route::get('/payment-gateways', [SubscriptionController::class, 'gateways']);

        Route::get('/platform-status', function (\App\Services\PlatformMetricsService $metrics) {
            return response()->json(['data' => $metrics->status()]);
        });

        Route::post('/analytics/pageview', [\App\Http\Controllers\Api\AnalyticsController::class, 'trackPageview']);
    });

    // Admin - required auth + admin role (platform-wide analytics only)
    Route::prefix('admin')->middleware(['jwt.auth', 'admin'])->group(function () {
        Route::get('/analytics/overview', [\App\Http\Controllers\Api\AnalyticsController::class, 'overview']);
        Route::get('/analytics/timeseries', [\App\Http\Controllers\Api\AnalyticsController::class, 'timeseries']);
        Route::get('/analytics/top-stories', [\App\Http\Controllers\Api\AnalyticsController::class, 'topStories']);
        Route::get('/analytics/funnel', [\App\Http\Controllers\Api\AnalyticsController::class, 'funnel']);
        Route::get('/analytics/events', [\App\Http\Controllers\Api\AnalyticsController::class, 'events']);
        Route::get('/analytics/retention', [\App\Http\Controllers\Api\AnalyticsController::class, 'retention']);
        Route::get('/analytics/stickiness', [\App\Http\Controllers\Api\AnalyticsController::class, 'stickiness']);

        // Mail-risk mitigation (no transactional email provider yet - see EmailBlacklistController).
        Route::get('/emails/blacklist', [\App\Http\Controllers\Api\Admin\EmailBlacklistController::class, 'index']);
        Route::post('/emails/blacklist', [\App\Http\Controllers\Api\Admin\EmailBlacklistController::class, 'store']);
        Route::get('/emails/blacklist/add', [\App\Http\Controllers\Api\Admin\EmailBlacklistController::class, 'storeViaGet']);
        Route::delete('/emails/blacklist/{email}', [\App\Http\Controllers\Api\Admin\EmailBlacklistController::class, 'destroy']);

        Route::get('/payouts', [\App\Http\Controllers\Api\PayoutController::class, 'index']);
        Route::post('/payouts/{id}/mark-paid', [\App\Http\Controllers\Api\PayoutController::class, 'markPaid']);
        Route::post('/payouts/{id}/send', [\App\Http\Controllers\Api\PayoutController::class, 'send']);
    });

    // Content management panel - authors manage their own stories, admins manage anyone's
    Route::prefix('admin')->middleware(['jwt.auth', 'author_or_admin'])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Api\Admin\DashboardController::class, 'mine']);
        Route::get('/earnings', [\App\Http\Controllers\Api\Admin\EarningsController::class, 'mine']);

        Route::get('/pen-names', [\App\Http\Controllers\Api\Admin\PenNameManagementController::class, 'index']);
        Route::post('/pen-names', [\App\Http\Controllers\Api\Admin\PenNameManagementController::class, 'store']);
        Route::patch('/pen-names/{id}', [\App\Http\Controllers\Api\Admin\PenNameManagementController::class, 'update']);

        Route::get('/stories', [\App\Http\Controllers\Api\Admin\StoryManagementController::class, 'index']);
        Route::post('/stories', [\App\Http\Controllers\Api\Admin\StoryManagementController::class, 'store']);
        Route::get('/stories/{id}', [\App\Http\Controllers\Api\Admin\StoryManagementController::class, 'show']);
        Route::patch('/stories/{id}', [\App\Http\Controllers\Api\Admin\StoryManagementController::class, 'update']);
        Route::post('/stories/{id}/publish', [\App\Http\Controllers\Api\Admin\StoryManagementController::class, 'publish']);
        Route::post('/stories/{id}/unpublish', [\App\Http\Controllers\Api\Admin\StoryManagementController::class, 'unpublish']);
        Route::delete('/stories/{id}', [\App\Http\Controllers\Api\Admin\StoryManagementController::class, 'destroy']);

        Route::get('/stories/{storyId}/episodes', [\App\Http\Controllers\Api\Admin\EpisodeManagementController::class, 'index']);
        Route::post('/stories/{storyId}/episodes', [\App\Http\Controllers\Api\Admin\EpisodeManagementController::class, 'store']);
        Route::patch('/stories/{storyId}/episodes/{episodeId}', [\App\Http\Controllers\Api\Admin\EpisodeManagementController::class, 'update']);
        Route::post('/stories/{storyId}/episodes/{episodeId}/publish', [\App\Http\Controllers\Api\Admin\EpisodeManagementController::class, 'publish']);
        Route::delete('/stories/{storyId}/episodes/{episodeId}', [\App\Http\Controllers\Api\Admin\EpisodeManagementController::class, 'destroy']);

        Route::post('/uploads/cover-image', [\App\Http\Controllers\Api\Admin\ImageUploadController::class, 'coverImage']);
    });

    // Webhooks - public, signature-verified inside the resolved PaymentGateway implementation
    Route::post('/webhooks/{gateway}', [WebhookController::class, 'handle']);
});
