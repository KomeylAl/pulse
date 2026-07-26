<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectPushSettingsRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\DeviceTokenResource;
use App\Http\Resources\ProjectResource;
use App\Models\DeviceToken;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return ProjectResource::collection($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::query()->create([
            'user_id' => $request->user()->id,
            'key' => Project::generateKeyFromName($request->string('name')->value()),
            'name' => $request->string('name')->value(),
            'type' => ProjectType::from($request->string('type')->value()),
            'api_key' => Project::generateApiKey(),
            'fcm_web_icon' => config('pulse.defaults.fcm_web_icon'),
            'fcm_default_link' => $request->input('fcm_default_link')
                ?: config('pulse.defaults.fcm_default_link'),
        ]);

        return response()->json([
            'message' => 'Project created successfully.',
            'project' => new ProjectResource($project, includeSecrets: true),
        ], 201);
    }

    public function show(Request $request, Project $project): ProjectResource
    {
        $this->authorizeOwner($request, $project);

        return new ProjectResource($project, includeSecrets: true);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorizeOwner($request, $project);

        $project->fill($request->validated());
        $project->save();

        return new ProjectResource($project->fresh(), includeSecrets: true);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorizeOwner($request, $project);
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully.']);
    }

    public function regenerateApiKey(Request $request, Project $project): JsonResponse
    {
        $this->authorizeOwner($request, $project);

        $project->update(['api_key' => Project::generateApiKey()]);

        return response()->json([
            'message' => 'API key regenerated.',
            'project' => new ProjectResource($project->fresh(), includeSecrets: true),
        ]);
    }

    public function updatePushSettings(UpdateProjectPushSettingsRequest $request, Project $project): ProjectResource
    {
        $this->authorizeOwner($request, $project);

        $data = [];

        if ($request->exists('firebase_credentials')) {
            $raw = $request->input('firebase_credentials');
            $data['firebase_credentials'] = filled($raw) ? trim((string) $raw) : null;
        }

        if ($request->exists('firebase_web_config')) {
            $config = $request->input('firebase_web_config');
            $data['firebase_web_config'] = filled($config) ? $config : null;
        }

        if ($request->exists('vapid_key')) {
            $vapid = $request->input('vapid_key');
            $data['vapid_key'] = filled($vapid) ? (string) $vapid : null;
        }

        if ($request->exists('fcm_web_icon')) {
            $data['fcm_web_icon'] = $request->input('fcm_web_icon') ?: config('pulse.defaults.fcm_web_icon');
        }

        if ($request->exists('fcm_default_link')) {
            $data['fcm_default_link'] = $request->input('fcm_default_link');
        }

        $project->update($data);

        return new ProjectResource($project->fresh(), includeSecrets: true);
    }

    public function devices(Request $request, Project $project): JsonResponse
    {
        $this->authorizeOwner($request, $project);

        $devices = DeviceToken::query()
            ->where('project_key', $project->key)
            ->latest()
            ->limit(200)
            ->get();

        $subscribers = DeviceToken::query()
            ->where('project_key', $project->key)
            ->where('is_active', true)
            ->whereNotNull('external_user_id')
            ->selectRaw('external_user_id, count(*) as devices_count, max(last_used_at) as last_used_at, max(created_at) as registered_at')
            ->groupBy('external_user_id')
            ->orderBy('external_user_id')
            ->get()
            ->map(fn ($row) => [
                'external_user_id' => $row->external_user_id,
                'devices_count' => (int) $row->devices_count,
                'last_used_at' => $row->last_used_at,
                'registered_at' => $row->registered_at,
            ])
            ->values();

        return response()->json([
            'devices' => DeviceTokenResource::collection($devices)->resolve(),
            'subscribers' => $subscribers,
            'stats' => [
                'total_devices' => DeviceToken::query()->where('project_key', $project->key)->count(),
                'active_devices' => DeviceToken::query()->where('project_key', $project->key)->where('is_active', true)->count(),
                'subscribers' => $subscribers->count(),
            ],
        ]);
    }

    public function integrationGuide(Request $request, Project $project): JsonResponse
    {
        $this->authorizeOwner($request, $project);

        $apiBase = rtrim((string) config('app.url'), '/').'/api/v1';
        $webConfig = $project->firebase_web_config ?? [
            'apiKey' => 'YOUR_API_KEY',
            'authDomain' => 'YOUR_PROJECT.firebaseapp.com',
            'projectId' => 'YOUR_PROJECT_ID',
            'storageBucket' => 'YOUR_PROJECT.appspot.com',
            'messagingSenderId' => 'YOUR_SENDER_ID',
            'appId' => 'YOUR_APP_ID',
        ];
        $vapid = $project->vapid_key ?: 'YOUR_VAPID_KEY';
        $apiKey = $project->api_key;

        return response()->json([
            'project' => new ProjectResource($project, includeSecrets: true),
            'steps' => [
                [
                    'title' => 'ساخت پروژه در Firebase Console',
                    'body' => 'به console.firebase.google.com بروید، یک پروژه بسازید و Cloud Messaging را فعال کنید.',
                ],
                [
                    'title' => 'دانلود Service Account',
                    'body' => 'Project Settings → Service accounts → Generate new private key. محتوای JSON را در تنظیمات Push همین صفحه paste کنید.',
                ],
                [
                    'title' => 'تنظیمات Web (برای PWA)',
                    'body' => 'Project Settings → Your apps → Web app. مقادیر apiKey، authDomain، projectId و ... را وارد کنید. سپس Cloud Messaging → Web Push certificates → VAPID key.',
                ],
                [
                    'title' => 'ثبت توکن دستگاه',
                    'body' => 'در اپ خود FCM token بگیرید و با هدر X-Pulse-Api-Key به Pulse ثبت کنید.',
                ],
            ],
            'samples' => [
                'register_device_curl' => "curl -X POST {$apiBase}/push/device-tokens \\\n  -H \"Content-Type: application/json\" \\\n  -H \"X-Pulse-Api-Key: {$apiKey}\" \\\n  -d '{\n    \"token\": \"FCM_DEVICE_TOKEN\",\n    \"platform\": \"pwa\",\n    \"external_user_id\": \"user-42\"\n  }'",
                'send_push_curl' => "curl -X POST {$apiBase}/push/send \\\n  -H \"Content-Type: application/json\" \\\n  -H \"X-Pulse-Api-Key: {$apiKey}\" \\\n  -d '{\n    \"title\": \"Hello\",\n    \"body\": \"Push from Pulse\",\n    \"external_user_id\": \"user-42\"\n  }'",
                'pwa_js' => $this->pwaSample($webConfig, $vapid, $apiBase, $apiKey),
                'android_kotlin' => $this->androidSample($apiBase, $apiKey),
            ],
        ]);
    }

    private function authorizeOwner(Request $request, Project $project): void
    {
        abort_unless($project->user_id === $request->user()->id, 403);
    }

    /**
     * @param  array<string, mixed>  $webConfig
     */
    private function pwaSample(array $webConfig, string $vapid, string $apiBase, string $apiKey): string
    {
        $configJson = json_encode($webConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<JS
import { initializeApp } from "firebase/app";
import { getMessaging, getToken, isSupported } from "firebase/messaging";

const firebaseConfig = {$configJson};

const app = initializeApp(firebaseConfig);

export async function registerPush(externalUserId) {
  if (!(await isSupported())) return null;

  const permission = await Notification.requestPermission();
  if (permission !== "granted") return null;

  const messaging = getMessaging(app);
  const registration = await navigator.serviceWorker.register("/firebase-messaging-sw.js");
  const token = await getToken(messaging, {
    vapidKey: "{$vapid}",
    serviceWorkerRegistration: registration,
  });

  await fetch("{$apiBase}/push/device-tokens", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Pulse-Api-Key": "{$apiKey}",
    },
    body: JSON.stringify({
      token,
      platform: "pwa",
      external_user_id: externalUserId,
      device_name: navigator.userAgent,
    }),
  });

  return token;
}
JS;
    }

    private function androidSample(string $apiBase, string $apiKey): string
    {
        return <<<KOTLIN
// After FirebaseMessaging.getInstance().token succeeds:
val token = /* FCM token */
val body = JSONObject()
  .put("token", token)
  .put("platform", "android")
  .put("external_user_id", currentUserId)
  .put("device_name", Build.MODEL)

val request = Request.Builder()
  .url("{$apiBase}/push/device-tokens")
  .addHeader("Content-Type", "application/json")
  .addHeader("X-Pulse-Api-Key", "{$apiKey}")
  .post(body.toString().toRequestBody("application/json".toMediaType()))
  .build()
KOTLIN;
    }
}
