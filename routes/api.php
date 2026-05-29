<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AnnouncementController,
    CommunicationBoardController,
    BoardPostController,
    BoardCommentController,
    CareerResourceController,
    AnalyticsController,
    NotificationController,
    MessageController,
    SearchController,
    ActivityController,
    DashboardController,
    DepartmentController,
    RealtimeController,
    OpportunityController,
};
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SsoController;
use App\Http\Middleware\{ValidateSSOToken, BlockStudents};

/*
|--------------------------------------------------------------------------
| SOA public endpoints (no SSO — service discovery & health)
|--------------------------------------------------------------------------
*/
Route::get('/health', [ServiceController::class, 'health']);

/*
|--------------------------------------------------------------------------
| Portal SSO handshake (session created here — no bearer on API v1)
|--------------------------------------------------------------------------
*/
Route::prefix('sso')->group(function () {
    Route::post('/exchange', [SsoController::class, 'exchange']);
    Route::post('/revoke', [SsoController::class, 'revoke']);
});
Route::prefix('v1/service')->group(function () {
    Route::get('/manifest', [ServiceController::class, 'manifest']);
    Route::get('/openapi', [ServiceController::class, 'openapi']);
});

/*
|--------------------------------------------------------------------------
| Versioned REST API (Portal SSO required)
|--------------------------------------------------------------------------
*/
Route::middleware([
    ValidateSSOToken::class,
    \App\Http\Middleware\LogUnauthorizedAccess::class,
    'throttle:careerconnect-api',
])->prefix('v1')->group(function () {
    Route::get('/auth/me', [\App\Http\Controllers\Api\AuthController::class, 'me']);

    // Recruitment / opportunities (students + staff)
    Route::prefix('opportunities')->group(function () {
        Route::get('/bootstrap', [OpportunityController::class, 'bootstrap']);
        Route::get('/reports', [OpportunityController::class, 'reports'])
            ->middleware('permission:opportunities.reports');

        Route::post('/jobs', [OpportunityController::class, 'storeJob'])
            ->middleware('permission:opportunities.manage');
        Route::put('/jobs/{id}', [OpportunityController::class, 'updateJob'])
            ->middleware('permission:opportunities.manage');
        Route::patch('/jobs/{id}/status', [OpportunityController::class, 'updateJobStatus'])
            ->middleware('permission:opportunities.manage');
        Route::delete('/jobs/{id}', [OpportunityController::class, 'destroyJob'])
            ->middleware('permission:opportunities.delete');

        Route::post('/internships', [OpportunityController::class, 'storeInternship'])
            ->middleware('permission:opportunities.manage');
        Route::put('/internships/{id}', [OpportunityController::class, 'updateInternship'])
            ->middleware('permission:opportunities.manage');
        Route::patch('/internships/{id}/status', [OpportunityController::class, 'updateInternshipStatus'])
            ->middleware('permission:opportunities.manage');
        Route::delete('/internships/{id}', [OpportunityController::class, 'destroyInternship'])
            ->middleware('permission:opportunities.delete');

        Route::post('/applications', [OpportunityController::class, 'storeApplication'])
            ->middleware('permission:opportunities.apply');
        Route::patch('/applications/{id}/status', [OpportunityController::class, 'updateApplicationStatus'])
            ->middleware('permission:opportunities.approve');
        Route::delete('/applications/{id}', [OpportunityController::class, 'destroyApplication'])
            ->middleware('permission:opportunities.delete');
    });

    // Faculty-only features
    Route::middleware(BlockStudents::class)->group(function () {
        Route::get('/realtime/poll', [RealtimeController::class, 'poll']);

        // Department coordination
        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::get('/departments/{department}', [DepartmentController::class, 'show']);

        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/analytics', [DashboardController::class, 'analytics']);

        // Announcements
        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show']);
        Route::post('/announcements', [AnnouncementController::class, 'store'])
            ->middleware('role:admin,instructor,admission_officer,career_officer');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy']);

        // Communication Boards
        Route::get('/boards', [CommunicationBoardController::class, 'index']);
        Route::get('/boards/{board}', [CommunicationBoardController::class, 'show']);
        Route::post('/boards', [CommunicationBoardController::class, 'store'])
            ->middleware('role:admin,instructor,admission_officer,career_officer');
        Route::put('/boards/{board}', [CommunicationBoardController::class, 'update']);
        Route::delete('/boards/{board}', [CommunicationBoardController::class, 'destroy']);

        // Board Posts
        Route::get('/boards/{board}/posts', [BoardPostController::class, 'index']);
        Route::get('/boards/{board}/posts/{post}', [BoardPostController::class, 'show']);
        Route::post('/boards/{board}/posts', [BoardPostController::class, 'store']);
        Route::put('/posts/{post}', [BoardPostController::class, 'update']);
        Route::delete('/posts/{post}', [BoardPostController::class, 'destroy']);

        // Board comments
        Route::get('/boards/{board}/posts/{post}/comments', [BoardCommentController::class, 'index']);
        Route::post('/boards/{board}/posts/{post}/comments', [BoardCommentController::class, 'store']);
        Route::put('/comments/{comment}', [BoardCommentController::class, 'update']);
        Route::delete('/comments/{comment}', [BoardCommentController::class, 'destroy']);

        // Career Resources
        Route::get('/resources/categories', [CareerResourceController::class, 'categories']);
        Route::get('/resources', [CareerResourceController::class, 'index']);
        Route::post('/resources', [CareerResourceController::class, 'store'])
            ->middleware('role:admin,instructor,librarian');
        Route::get('/resources/{resource}', [CareerResourceController::class, 'show']);
        Route::get('/resources/{resource}/download', [CareerResourceController::class, 'download']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/{notification}/archive', [NotificationController::class, 'archive']);

        // Messages
        Route::get('/messages/directory', [MessageController::class, 'facultyDirectory']);
        Route::get('/messages/threads', [MessageController::class, 'threads']);
        Route::post('/messages/threads', [MessageController::class, 'createThread'])
            ->middleware('role:admin,instructor');
        Route::get('/messages/threads/{thread}', [MessageController::class, 'getThread']);
        Route::post('/messages/threads/{thread}/send', [MessageController::class, 'sendMessage'])
            ->middleware('role:admin,instructor');
        Route::post('/messages/{message}/read', [MessageController::class, 'markMessageAsRead']);

        // Search
        Route::get('/search', [SearchController::class, 'search']);

        // Activity
        Route::get('/activity', [ActivityController::class, 'index']);
        Route::get('/activity/system', [ActivityController::class, 'getSystemActivity']);

        // Analytics (SQL views / procedures)
        Route::get('/analytics/faculty-activity', [AnalyticsController::class, 'facultyActivity']);
        Route::get('/analytics/announcement-delivery', [AnalyticsController::class, 'announcementDelivery']);
        Route::get('/analytics/engagement', [AnalyticsController::class, 'engagement']);
    });
});
