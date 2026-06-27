<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SlaPolicyController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Authenticated + tenant-scoped (SetTenant runs via api middleware group)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Tickets
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
        Route::match(['put', 'patch'], '/tickets/{ticket}', [TicketController::class, 'update']);
        Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);
        Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign']);
        Route::post('/tickets/{ticket}/claim', [TicketController::class, 'claim']);

        // Comments
        Route::post('/tickets/{ticket}/comments', [CommentController::class, 'store']);

        // Tags
        Route::get('/tags', [TagController::class, 'index']);
        Route::post('/tags', [TagController::class, 'store']);

        // Users / agents
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/agents', [UserController::class, 'agents']);

        // SLA policies
        Route::get('/sla-policies', [SlaPolicyController::class, 'index']);
        Route::match(['put', 'patch'], '/sla-policies/{sla_policy}', [SlaPolicyController::class, 'update']);

        // Dashboard
        Route::get('/dashboard/metrics', [DashboardController::class, 'metrics']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    });
});
