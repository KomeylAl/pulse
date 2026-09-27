<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationPriority;
use App\Http\Controllers\Concerns\ResolvesPulseProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Email\RegisterEmailContactRequest;
use App\Http\Requests\Email\SendEmailNotificationRequest;
use App\Http\Resources\EmailContactResource;
use App\Http\Resources\NotificationLogResource;
use App\Models\EmailContact;
use App\Models\Project;
use App\Services\Notifications\Email\ResendWebhookHandler;
use App\Services\Notifications\Email\ResendWebhookVerifier;
use App\Services\Notifications\NotificationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EmailController extends Controller
{
    use ResolvesPulseProject;

    public function __construct(
        private readonly NotificationManager $notificationManager,
        private readonly ResendWebhookVerifier $webhookVerifier,
        private readonly ResendWebhookHandler $webhookHandler,
    ) {}

    public function registerContact(RegisterEmailContactRequest $request): EmailContactResource
    {
        $email = strtolower($request->string('email')->value());

        $contact = EmailContact::query()->updateOrCreate(
            [
                'project_key' => $this->pulseProjectKey($request),
                'email' => $email,
            ],
            [
                'name' => $request->string('name')->toString() ?: null,
                'external_user_id' => $request->string('external_user_id')->toString() ?: null,
                'is_active' => true,
                'unsubscribed_at' => null,
            ],
        );

        return EmailContactResource::make($contact);
    }

    public function deactivateContact(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        EmailContact::query()
            ->where('project_key', $this->pulseProjectKey($request))
            ->where('email', strtolower($request->string('email')->value()))
            ->update([
                'is_active' => false,
                'unsubscribed_at' => now(),
            ]);

        return response()->json(['message' => 'Email contact deactivated.']);
    }

    public function send(SendEmailNotificationRequest $request): JsonResponse
    {
        $projectKey = $this->pulseProjectKey($request);
        $priority = $request->filled('priority')
            ? NotificationPriority::from($request->string('priority')->value())
            : NotificationPriority::Normal;
        $data = $request->input('data', []);

        if ($request->filled('html')) {
            $data['html'] = $request->string('html')->value();
        }

        try {
            $logs = $request->filled('to')
                ? $this->notificationManager->sendEmail(
                    projectKey: $projectKey,
                    addresses: $request->input('to'),
                    title: $request->string('title')->value(),
                    body: $request->string('body')->value(),
                    data: $data,
                    priority: $priority,
                    scheduledAt: $request->date('scheduled_at'),
                    externalUserId: $request->filled('external_user_id')
                        ? $request->string('external_user_id')->value()
                        : null,
                    replyTo: $request->filled('reply_to') ? $request->string('reply_to')->value() : null,
                )
                : $this->notificationManager->sendEmailToExternalUser(
                    projectKey: $projectKey,
                    externalUserId: $request->string('external_user_id')->value(),
                    title: $request->string('title')->value(),
                    body: $request->string('body')->value(),
                    data: $data,
                    priority: $priority,
                    scheduledAt: $request->date('scheduled_at'),
                    replyTo: $request->filled('reply_to') ? $request->string('reply_to')->value() : null,
                );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $logs->each->refresh();

        return response()->json([
            'message' => 'Email notification queued successfully.',
            'notifications' => NotificationLogResource::collection($logs),
        ], 202);
    }

    public function webhook(Request $request, Project $project): JsonResponse
    {
        if (blank($project->resend_webhook_secret)) {
            return response()->json([
                'message' => 'Webhook secret is not configured for this project.',
            ], 401);
        }

        $verified = $this->webhookVerifier->verify(
            payload: $request->getContent(),
            secret: (string) $project->resend_webhook_secret,
            id: $request->header('svix-id'),
            timestamp: $request->header('svix-timestamp'),
            signatureHeader: $request->header('svix-signature'),
        );

        if (! $verified) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        /** @var array<string, mixed> $event */
        $event = $request->json()->all();
        $applied = $this->webhookHandler->handle($project, $event);

        return response()->json([
            'message' => $applied ? 'Webhook applied.' : 'Webhook ignored.',
        ]);
    }
}
