<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Enums\ProjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Email\SendTestEmailRequest;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectEmailSettingsRequest;
use App\Http\Requests\Projects\UpdateProjectPushSettingsRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\DeviceTokenResource;
use App\Http\Resources\EmailContactResource;
use App\Http\Resources\NotificationLogResource;
use App\Http\Resources\ProjectResource;
use App\Models\DeviceToken;
use App\Models\EmailContact;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Services\Notifications\Email\ResendClient;
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

    public function updateEmailSettings(UpdateProjectEmailSettingsRequest $request, Project $project): ProjectResource
    {
        $this->authorizeOwner($request, $project);

        $data = [
            'email_from_address' => strtolower(trim($request->string('email_from_address')->value())),
            'email_from_name' => $request->filled('email_from_name')
                ? trim($request->string('email_from_name')->value())
                : null,
            'email_reply_to' => $request->filled('email_reply_to')
                ? strtolower(trim($request->string('email_reply_to')->value()))
                : null,
        ];

        if ($request->filled('resend_api_key')) {
            $data['resend_api_key'] = trim($request->string('resend_api_key')->value());
        }

        if ($request->filled('resend_webhook_secret')) {
            $data['resend_webhook_secret'] = trim($request->string('resend_webhook_secret')->value());
        }

        $project->update($data);

        return new ProjectResource($project->fresh(), includeSecrets: true);
    }

    public function emailContacts(Request $request, Project $project): JsonResponse
    {
        $this->authorizeOwner($request, $project);

        $contacts = EmailContact::query()
            ->where('project_key', $project->key)
            ->latest()
            ->limit(200)
            ->get();

        return response()->json([
            'contacts' => EmailContactResource::collection($contacts)->resolve(),
            'stats' => [
                'total' => EmailContact::query()->where('project_key', $project->key)->count(),
                'active' => EmailContact::query()->where('project_key', $project->key)->where('is_active', true)->count(),
            ],
        ]);
    }

    public function sendTestEmail(SendTestEmailRequest $request, Project $project, ResendClient $resend): JsonResponse
    {
        $this->authorizeOwner($request, $project);

        if (! $project->hasEmailChannel()) {
            return response()->json([
                'message' => 'اول کلید Resend و آدرس فرستنده را ذخیره کنید.',
            ], 422);
        }

        $to = strtolower($request->string('to')->value());
        $subject = $request->filled('subject')
            ? $request->string('subject')->value()
            : 'پیام آزمایشی Pulse';
        $body = $request->filled('body')
            ? $request->string('body')->value()
            : 'اگر این نامه را می‌بینید، کانال ایمیل پروژه درست پیکربندی شده است.';

        $inactive = EmailContact::query()
            ->where('project_key', $project->key)
            ->where('email', $to)
            ->where('is_active', false)
            ->exists();

        if ($inactive) {
            return response()->json([
                'message' => 'این گیرنده لغو اشتراک شده یا در لیست مسدود است.',
            ], 422);
        }

        $log = NotificationLog::query()->create([
            'project_key' => $project->key,
            'channel' => NotificationChannelType::Email,
            'title' => $subject,
            'body' => $body,
            'status' => NotificationStatus::Processing,
            'priority' => NotificationPriority::Normal,
            'recipient' => $to,
            'attempts' => 1,
        ]);

        try {
            $messageId = $resend->send(
                apiKey: (string) $project->resend_api_key,
                from: $resend->formatFrom($project->email_from_name, (string) $project->email_from_address),
                to: [$to],
                subject: $subject,
                html: $resend->renderHtml($subject, $body, null),
                text: $resend->plainText($subject, $body),
                replyTo: $project->email_reply_to,
                tags: [
                    ['name' => 'project_key', 'value' => $project->key],
                    ['name' => 'log_id', 'value' => (string) $log->id],
                ],
                idempotencyKey: 'pulse-test-'.$log->id,
            );
        } catch (\Throwable $exception) {
            $log->update([
                'status' => NotificationStatus::Failed,
                'error_message' => $exception->getMessage(),
                'delivery_status' => 'failed',
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
                'notification' => new NotificationLogResource($log->fresh()),
            ], 422);
        }

        $log->update([
            'status' => NotificationStatus::Sent,
            'sent_at' => now(),
            'provider_message_id' => $messageId,
            'delivery_status' => 'sent',
            'error_message' => null,
        ]);

        return response()->json([
            'message' => 'ایمیل آزمایشی ارسال شد.',
            'provider_message_id' => $messageId,
            'notification' => new NotificationLogResource($log->fresh()),
        ]);
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
            'email' => $this->emailGuide($project, $apiBase, $apiKey),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emailGuide(Project $project, string $apiBase, string $apiKey): array
    {
        $from = $project->email_from_address ?: 'hello@notifications.example.com';
        $fromName = $project->email_from_name ?: 'Pulse';
        $webhookUrl = $apiBase.'/email/webhook/'.$project->key;

        return [
            'webhook_url' => $webhookUrl,
            'steps' => [
                [
                    'title' => 'ساخت اکانت در Resend',
                    'body' => 'به resend.com بروید و یک اکانت بسازید. هر پروژه Pulse کلید و دامنهٔ جداگانهٔ خودش را دارد.',
                ],
                [
                    'title' => 'افزودن دامنه',
                    'body' => 'در داشبورد Resend به Domains بروید و Add Domain را بزنید. بهتر است از یک ساب‌دامین مثل notifications.example.com استفاده کنید، نه خود دامنهٔ اصلی، تا اعتبار دامنهٔ سایت حفظ شود. منطقه‌ای را انتخاب کنید که به بیشتر گیرنده‌ها نزدیک‌تر است.',
                ],
                [
                    'title' => 'ثبت رکوردهای DNS',
                    'body' => 'رکوردهای DKIM و SPF را که Resend نشان می‌دهد عیناً در DNS دامنه کپی کنید. نوع رکورد ممکن است TXT، MX یا CNAME باشد. اگر CNAME است، پروکسی را خاموش کنید (در Cloudflare فقط DNS، بدون ابر نارنجی). وریفای اغلب زیر ۱۵ دقیقه انجام می‌شود و گاهی تا ۷۲ ساعت طول می‌کشد. تا وقتی وضعیت دامنه verified نشده، ارسال به گیرندهٔ واقعی با خطای 403 برمی‌گردد. وضعیت partially verified هم اجازهٔ ارسال می‌دهد.',
                ],
                [
                    'title' => 'رکورد DMARC',
                    'body' => 'بعد از وریفای دامنه یک رکورد DMARC هم اضافه کنید. این کار اعتماد صندوق‌های ایمیل را بیشتر می‌کند و جعل دامنه را سخت‌تر می‌کند.',
                ],
                [
                    'title' => 'ساخت API Key',
                    'body' => 'در API Keys یک کلید با دسترسی Sending access بسازید و آن را به همین دامنه محدود کنید. کلید فقط یک بار نشان داده می‌شود. آدرس فرستنده را جدا نمی‌سازید: بعد از وریفای، هر آدرسی روی آن دامنه (مثلاً '.$from.') را می‌توانید در فیلد From بگذارید.',
                ],
                [
                    'title' => 'وب‌هوک وضعیت تحویل',
                    'body' => 'اختیاری ولی مفید است. در Resend یک webhook بسازید، آدرس '.$webhookUrl.' را بدهید و رویدادهای email.sent، email.delivered، email.bounced، email.complained، email.failed و email.suppressed را فعال کنید. Signing secret را در فیلد پایین paste کنید تا Pulse امضای درخواست را بررسی کند و وضعیت را در تاریخچه بنویسد. برگشت، اسپم و سرکوب، مخاطب را غیرفعال می‌کند تا اعتبار دامنه آسیب نبیند.',
                ],
                [
                    'title' => 'ارسال آزمایشی',
                    'body' => 'از فرم پایین یک نامه بفرستید. برای تست بدون آسیب به اعتبار دامنه از delivered@resend.dev ، bounced@resend.dev یا complained@resend.dev استفاده کنید. به آدرس ساختگی نفرستید. دامنهٔ resend.dev فقط به ایمیل خود اکانت Resend نامه می‌فرستد و برای محصول کافی نیست.',
                ],
            ],
            'samples' => [
                'register_contact_curl' => "curl -X POST {$apiBase}/email/contacts \\\n  -H \"Content-Type: application/json\" \\\n  -H \"X-Pulse-Api-Key: {$apiKey}\" \\\n  -d '{\n    \"email\": \"user@example.com\",\n    \"name\": \"Ali\",\n    \"external_user_id\": \"user-42\"\n  }'",
                'send_email_curl' => "curl -X POST {$apiBase}/email/send \\\n  -H \"Content-Type: application/json\" \\\n  -H \"X-Pulse-Api-Key: {$apiKey}\" \\\n  -d '{\n    \"to\": [\"user@example.com\"],\n    \"title\": \"Hello\",\n    \"body\": \"Email from {$fromName}\"\n  }'",
            ],
        ];
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
