<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationPriority;
use App\Http\Controllers\Concerns\ResolvesPulseProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Push\RegisterPushDeviceTokenRequest;
use App\Http\Requests\Push\SendPushNotificationRequest;
use App\Http\Resources\DeviceTokenResource;
use App\Http\Resources\NotificationLogResource;
use App\Models\DeviceToken;
use App\Services\Notifications\NotificationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    use ResolvesPulseProject;

    public function __construct(
        private readonly NotificationManager $notificationManager,
    ) {}

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'project' => $this->pulseProject($request)->toPublicArray(),
        ]);
    }

    public function registerDeviceToken(RegisterPushDeviceTokenRequest $request): DeviceTokenResource
    {
        $projectKey = $this->pulseProjectKey($request);
        $tokenValue = $request->string('token')->value();
        $externalUserId = $request->string('external_user_id')->toString() ?: null;
        $platform = $request->string('platform')->value();
        $deviceId = $request->string('device_id')->toString() ?: null;

        $token = DeviceToken::query()->updateOrCreate(
            [
                'project_key' => $projectKey,
                'token' => $tokenValue,
            ],
            [
                'external_user_id' => $externalUserId,
                'platform' => $platform,
                'device_name' => $request->string('device_name')->toString() ?: null,
                'device_id' => $deviceId,
                'is_active' => true,
                'last_used_at' => now(),
            ],
        );

        // Keep a single active token per user/device so refreshes don't multiply pushes.
        $stale = DeviceToken::query()
            ->where('project_key', $projectKey)
            ->where('platform', $platform)
            ->whereKeyNot($token->id)
            ->where('is_active', true);

        if (filled($deviceId)) {
            $stale->where('device_id', $deviceId);
        } elseif (filled($externalUserId)) {
            $stale->where('external_user_id', $externalUserId);
        } else {
            $stale->whereNull('external_user_id')->whereNull('device_id');
            // Without identity we only deactivate exact duplicates (same token already handled).
            $stale->whereRaw('1 = 0');
        }

        $stale->update(['is_active' => false]);

        return DeviceTokenResource::make($token);
    }

    public function deactivateDeviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        DeviceToken::query()
            ->where('project_key', $this->pulseProjectKey($request))
            ->where('token', $request->string('token')->value())
            ->update(['is_active' => false]);

        return response()->json(['message' => 'Device token deactivated.']);
    }

    public function send(SendPushNotificationRequest $request): JsonResponse
    {
        $projectKey = $this->pulseProjectKey($request);
        $priority = $request->filled('priority')
            ? NotificationPriority::from($request->string('priority')->value())
            : NotificationPriority::Normal;

        if ($request->filled('tokens')) {
            $logs = $this->notificationManager->sendPushToTokens(
                projectKey: $projectKey,
                tokens: $request->input('tokens'),
                title: $request->string('title')->value(),
                body: $request->string('body')->value(),
                data: $request->input('data', []),
                priority: $priority,
                scheduledAt: $request->date('scheduled_at'),
            );
        } else {
            $logs = $this->notificationManager->sendPushToExternalUser(
                projectKey: $projectKey,
                externalUserId: $request->string('external_user_id')->value(),
                title: $request->string('title')->value(),
                body: $request->string('body')->value(),
                data: $request->input('data', []),
                priority: $priority,
                scheduledAt: $request->date('scheduled_at'),
            );
        }

        if ($logs->isEmpty()) {
            return response()->json([
                'message' => 'No active device tokens found for the given target.',
            ], 422);
        }

        return response()->json([
            'message' => 'Push notification queued successfully.',
            'notifications' => NotificationLogResource::collection($logs),
        ], 202);
    }
}
