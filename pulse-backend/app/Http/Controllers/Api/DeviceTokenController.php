<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesPulseProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\RegisterDeviceTokenRequest;
use App\Http\Resources\DeviceTokenResource;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceTokenController extends Controller
{
    use ResolvesPulseProject;

    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = $request->user()
            ->deviceTokens()
            ->where('project_key', $this->pulseProjectKey($request))
            ->latest()
            ->paginate(20);

        return DeviceTokenResource::collection($tokens);
    }

    public function store(RegisterDeviceTokenRequest $request): DeviceTokenResource
    {
        $projectKey = $this->pulseProjectKey($request);
        $platform = $request->string('platform')->value();
        $deviceId = $request->string('device_id')->toString() ?: null;

        $token = DeviceToken::query()->updateOrCreate(
            [
                'project_key' => $projectKey,
                'token' => $request->string('token')->value(),
            ],
            [
                'user_id' => $request->user()->id,
                'platform' => $platform,
                'device_name' => $request->string('device_name')->toString() ?: null,
                'device_id' => $deviceId,
                'is_active' => true,
                'last_used_at' => now(),
            ],
        );

        DeviceToken::query()
            ->where('project_key', $projectKey)
            ->where('user_id', $request->user()->id)
            ->where('platform', $platform)
            ->whereKeyNot($token->id)
            ->where('is_active', true)
            ->when(
                filled($deviceId),
                fn ($query) => $query->where('device_id', $deviceId),
            )
            ->update(['is_active' => false]);

        return DeviceTokenResource::make($token);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $request->user()
            ->deviceTokens()
            ->where('project_key', $this->pulseProjectKey($request))
            ->where('token', $token)
            ->update(['is_active' => false]);

        return response()->json(['message' => 'Device token deactivated.']);
    }
}
