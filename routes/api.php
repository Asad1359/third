<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminChatController;
use App\Http\Controllers\Api\AdminCannedResponseController;
use App\Http\Controllers\Api\BagController;
use App\Http\Controllers\Api\CallController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GroupChatController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\WikipediaController;

/*
|==========================================================================
| PUBLIC ROUTES
|==========================================================================
| Koi authentication nahi chahiye
|==========================================================================
*/

/*
|--------------------------------------------------------------------------
| AUTH (needs `web` middleware so sessions work — required for OTP)
|--------------------------------------------------------------------------
*/

Route::middleware('web')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Registration
    |----------------------------------------------------------------------
    */
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-registration-otp', [AuthController::class, 'verifyRegistrationOtp']);
    Route::post('/resend-registration-otp', [AuthController::class, 'resendRegistrationOtp']);

    /*
    |----------------------------------------------------------------------
    | Login
    |----------------------------------------------------------------------
    */
    Route::post('/login', [AuthController::class, 'login']);

    /*
    |----------------------------------------------------------------------
    | Forgot Password
    |----------------------------------------------------------------------
    */
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-password-otp', [AuthController::class, 'verifyPasswordResetOtp']);
    Route::post('/resend-password-otp', [AuthController::class, 'resendPasswordResetOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| PUBLIC BAGS
|--------------------------------------------------------------------------
*/

Route::get('/bags', [BagController::class, 'index']);
Route::get('/bags/{id}', [BagController::class, 'show']);

/*
|--------------------------------------------------------------------------
| MARKETPLACE CALLBACK (from Project 2)
|--------------------------------------------------------------------------
*/

Route::post('/marketplace/callback', [MarketplaceController::class, 'marketplaceCallback']);

/*
|==========================================================================
| AUTHENTICATED ROUTES
|==========================================================================
| Sanctum + Web session middleware
|==========================================================================
*/

Route::middleware(['web', 'auth:sanctum'])->group(function () {

    /*
    |----------------------------------------------------------------------
    | AUTH / CURRENT USER / LOGOUT
    |----------------------------------------------------------------------
    */
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |----------------------------------------------------------------------
    | PROFILE
    |----------------------------------------------------------------------
    */
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/image', [ProfileController::class, 'updateImage']);
    Route::post('/profile/change-password', [ProfileController::class, 'updatePassword']);
    Route::put('/profile/theme', [ProfileController::class, 'updateTheme']);

    /*
    |----------------------------------------------------------------------
    | ALL USERS (for chat / group pickers)
    |----------------------------------------------------------------------
    */
    Route::get('/user/all', [DashboardController::class, 'user']);

    /*
    |======================================================================
    | CALLS (Voice / Video via WebRTC + Reverb)
    |======================================================================
    */
    Route::prefix('calls')->group(function () {
        Route::get('/',            [CallController::class, 'index']);
        Route::post('start',       [CallController::class, 'start']);
        Route::post('{id}/accept', [CallController::class, 'accept']);
        Route::post('{id}/reject', [CallController::class, 'reject']);
        Route::post('{id}/end',    [CallController::class, 'end']);
        Route::post('{id}/signal', [CallController::class, 'signal']);
    });

    /*
    |======================================================================
    | CHAT — USER SIDE
    |======================================================================
    */
    Route::prefix('chat')->group(function () {

        /*
        |------------------------------------------------------------------
        | SUPPORT CHAT (ChatWidget.vue)
        |------------------------------------------------------------------
        */
        Route::get('support', [ChatController::class, 'supportConversation']);
        Route::post('support/messages', [ChatController::class, 'sendSupportMessage']);
        Route::post('support/read', [ChatController::class, 'markSupportAsRead']);
        Route::post('support/typing', [ChatController::class, 'supportTyping']);
        Route::post('support/rate', [ChatController::class, 'supportRate']);

        /*
        |------------------------------------------------------------------
        | SHARED — Messages
        |------------------------------------------------------------------
        */
        Route::delete('messages/{id}', [ChatController::class, 'deleteMessage']);
        Route::post('messages/{id}/star', [ChatController::class, 'toggleStar']);

        /*
        |------------------------------------------------------------------
        | UNREAD COUNT + SEARCH
        |------------------------------------------------------------------
        */
        Route::get('unread-count', [ChatController::class, 'unreadCount']);
        Route::get('search', [ChatController::class, 'search']);

        /*
        |------------------------------------------------------------------
        | 1-TO-1 CONVERSATIONS (UserChat.vue)
        |------------------------------------------------------------------
        */
        Route::get('conversations', [ChatController::class, 'conversations']);
        Route::post('conversations', [ChatController::class, 'startConversation']);
        Route::get('conversations/{id}', [ChatController::class, 'show']);
        Route::get('conversations/{id}/messages', [ChatController::class, 'messages']);
        Route::post('conversations/{id}/messages', [ChatController::class, 'sendMessage']);
        Route::post('conversations/{id}/read', [ChatController::class, 'markAsRead']);
        Route::post('conversations/{id}/typing', [ChatController::class, 'typing']);
        Route::post('conversations/{id}/rate', [ChatController::class, 'rate']);

        /*
        |------------------------------------------------------------------
        | GROUPS
        |------------------------------------------------------------------
        */
        Route::get('groups', [GroupChatController::class, 'index']);
        Route::post('groups', [GroupChatController::class, 'store']);
        Route::get('groups/{id}', [GroupChatController::class, 'show']);
        Route::get('groups/{id}/messages', [GroupChatController::class, 'messages']);
        Route::post('groups/{id}/messages', [GroupChatController::class, 'sendMessage']);
        Route::post('groups/{id}/members', [GroupChatController::class, 'addMember']);
        Route::delete('groups/{id}/members/{userId}', [GroupChatController::class, 'removeMember']);
        Route::post('groups/{id}/leave', [GroupChatController::class, 'leave']);
    });

    /*
    |======================================================================
    | MARKETPLACE
    |======================================================================
    */
    Route::prefix('marketplace')->group(function () {
        Route::get('/', [MarketplaceController::class, 'index']);
        Route::get('products', [MarketplaceController::class, 'products']);
        Route::post('search', [MarketplaceController::class, 'search']);
        Route::get('status/{id}', [MarketplaceController::class, 'status']);
    });

    /*
    |======================================================================
    | WIKIPEDIA
    |======================================================================
    */
    Route::prefix('wikipedia')->group(function () {
        Route::get('/', [WikipediaController::class, 'index']);
        Route::post('search', [WikipediaController::class, 'search']);
    });

    /*
    |======================================================================
    | ADMIN
    |======================================================================
    */
    Route::middleware('admin')->prefix('admin')->group(function () {

        /*
        |------------------------------------------------------------------
        | DASHBOARD
        |------------------------------------------------------------------
        */
        Route::get('dashboard', [DashboardController::class, 'admin']);

        /*
        |------------------------------------------------------------------
        | ADMIN PROFILE
        |------------------------------------------------------------------
        */
        Route::get('profile', [ProfileController::class, 'adminProfile']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::post('profile/image', [ProfileController::class, 'updateImage']);
        Route::post('change-password', [ProfileController::class, 'updateAdminPassword']);
        Route::put('theme', [ProfileController::class, 'updateAdminTheme']);

        /*
        |------------------------------------------------------------------
        | USER MANAGEMENT
        |------------------------------------------------------------------
        */
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']);
            Route::post('/', [AdminUserController::class, 'store']);
            Route::get('{id}', [AdminUserController::class, 'show']);
            Route::put('{id}', [AdminUserController::class, 'update']);
            Route::delete('{id}', [AdminUserController::class, 'destroy']);
            Route::patch('{id}/status', [AdminUserController::class, 'toggleStatus']);
            Route::patch('{id}/activate', [AdminUserController::class, 'activate']);
            Route::patch('{id}/deactivate', [AdminUserController::class, 'deactivate']);
            Route::post('{id}/change-password', [AdminUserController::class, 'changePassword']);
        });

        /*
        |------------------------------------------------------------------
        | BAGS CRUD
        |------------------------------------------------------------------
        */
        Route::prefix('bags')->group(function () {
            Route::get('/', [BagController::class, 'index']);
            Route::post('/', [BagController::class, 'store']);
            Route::get('{id}', [BagController::class, 'show']);
            Route::put('{id}', [BagController::class, 'update']);
            Route::delete('{id}', [BagController::class, 'destroy']);
        });

        /*
        |------------------------------------------------------------------
        | ADMIN CHAT
        |------------------------------------------------------------------
        */
        Route::prefix('chat')->group(function () {

            /*
            |--------------------------------------------------------------
            | Conversations
            |--------------------------------------------------------------
            */
            Route::get('conversations', [AdminChatController::class, 'conversations']);
            Route::get('conversations/{id}', [AdminChatController::class, 'show']);
            Route::delete('conversations/{id}', [AdminChatController::class, 'destroy']);

            /*
            |--------------------------------------------------------------
            | Messages
            |--------------------------------------------------------------
            */
            Route::get('conversations/{id}/messages', [AdminChatController::class, 'messages']);
            Route::post('conversations/{id}/messages', [AdminChatController::class, 'sendMessage']);

            /*
            |--------------------------------------------------------------
            | Read / typing
            |--------------------------------------------------------------
            */
            Route::post('conversations/{id}/read', [AdminChatController::class, 'markAsRead']);
            Route::post('conversations/{id}/typing', [AdminChatController::class, 'typing']);

            /*
            |--------------------------------------------------------------
            | Canned responses
            |--------------------------------------------------------------
            */
            Route::get('canned-responses', [AdminChatController::class, 'cannedResponses']);

            /*
            |--------------------------------------------------------------
            | Search
            |--------------------------------------------------------------
            */
            Route::get('search', [AdminChatController::class, 'search']);

            /*
            |--------------------------------------------------------------
            | Actions
            |--------------------------------------------------------------
            */
            Route::post('conversations/{id}/assign', [AdminChatController::class, 'assign']);
            Route::post('conversations/{id}/block', [AdminChatController::class, 'block']);
            Route::post('conversations/{id}/unblock', [AdminChatController::class, 'unblock']);
            Route::post('conversations/{id}/clear', [AdminChatController::class, 'clear']);
            Route::post('conversations/{id}/archive', [AdminChatController::class, 'archive']);
            Route::post('conversations/{id}/unarchive', [AdminChatController::class, 'unarchive']);

            /*
            |--------------------------------------------------------------
            | Messages management
            |--------------------------------------------------------------
            */
            Route::delete('messages/{id}', [AdminChatController::class, 'deleteMessage']);
            Route::post('messages/{id}/star', [AdminChatController::class, 'toggleStar']);

            /*
            |--------------------------------------------------------------
            | Ratings
            |--------------------------------------------------------------
            */
            Route::get('ratings', [AdminChatController::class, 'ratings']);
        });

        /*
        |------------------------------------------------------------------
        | CANNED RESPONSES CRUD
        |------------------------------------------------------------------
        */
        Route::prefix('canned-responses')->group(function () {
            Route::get('/', [AdminCannedResponseController::class, 'index']);
            Route::post('/', [AdminCannedResponseController::class, 'store']);
            Route::put('{id}', [AdminCannedResponseController::class, 'update']);
            Route::delete('{id}', [AdminCannedResponseController::class, 'destroy']);
        });
    });
});

/*
|==========================================================================
| FALLBACK
|==========================================================================
| Unknown API routes ke liye JSON return karein (HTML nahi)
|==========================================================================
*/

Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found.',
    ], 404);
});