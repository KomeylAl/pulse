<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\EmailController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\PushController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\AuthenticatePulseProject;
use App\Http\Middleware\SetPulseProject;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Client apps authenticate with project API key (X-Pulse-Api-Key).
    Route::middleware(AuthenticatePulseProject::class)->prefix('push')->group(function (): void {
        Route::get('me', [PushController::class, 'me']);
        Route::post('device-tokens', [PushController::class, 'registerDeviceToken']);
        Route::delete('device-tokens', [PushController::class, 'deactivateDeviceToken']);
        Route::post('send', [PushController::class, 'send']);
    });

    Route::middleware(AuthenticatePulseProject::class)->prefix('email')->group(function (): void {
        Route::post('contacts', [EmailController::class, 'registerContact']);
        Route::delete('contacts', [EmailController::class, 'deactivateContact']);
        Route::post('send', [EmailController::class, 'send']);
    });

    Route::post('email/webhook/{project:key}', [EmailController::class, 'webhook']);

    Route::middleware(AuthenticatePulseProject::class)->get('projects/me', [PushController::class, 'me']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store']);
        Route::get('projects/{project:key}', [ProjectController::class, 'show']);
        Route::put('projects/{project:key}', [ProjectController::class, 'update']);
        Route::delete('projects/{project:key}', [ProjectController::class, 'destroy']);
        Route::post('projects/{project:key}/regenerate-api-key', [ProjectController::class, 'regenerateApiKey']);
        Route::put('projects/{project:key}/push-settings', [ProjectController::class, 'updatePushSettings']);
        Route::put('projects/{project:key}/email-settings', [ProjectController::class, 'updateEmailSettings']);
        Route::get('projects/{project:key}/email-contacts', [ProjectController::class, 'emailContacts']);
        Route::post('projects/{project:key}/email/test', [ProjectController::class, 'sendTestEmail']);
        Route::get('projects/{project:key}/integration-guide', [ProjectController::class, 'integrationGuide']);
        Route::get('projects/{project:key}/devices', [ProjectController::class, 'devices']);

        Route::middleware(SetPulseProject::class)->group(function (): void {
            Route::get('users', [UserController::class, 'index']);

            Route::get('device-tokens', [DeviceTokenController::class, 'index']);
            Route::post('device-tokens', [DeviceTokenController::class, 'store']);
            Route::delete('device-tokens/{token}', [DeviceTokenController::class, 'destroy'])
                ->where('token', '.*');

            Route::get('notifications/stats', [NotificationController::class, 'stats']);
            Route::get('notifications', [NotificationController::class, 'index']);
            Route::get('notifications/{notification}', [NotificationController::class, 'show']);
            Route::post('notifications/send', [NotificationController::class, 'send']);
            Route::post('notifications/send-bulk', [NotificationController::class, 'sendBulk']);
            Route::post('notifications/broadcast', [NotificationController::class, 'broadcast']);
            Route::post('notifications/project-push', [NotificationController::class, 'sendProjectPush']);
            Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

            Route::get('campaigns', [NotificationController::class, 'campaigns']);
            Route::post('campaigns', [NotificationController::class, 'createCampaign']);
            Route::post('campaigns/recurring', [NotificationController::class, 'createRecurring']);
            Route::get('campaigns/{campaign}', [NotificationController::class, 'showCampaign']);
            Route::post('campaigns/{campaign}/cancel', [NotificationController::class, 'cancelCampaign']);
        });
    });
});
