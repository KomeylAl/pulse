<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Http\Controllers\Concerns\ResolvesPulseProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\BroadcastNotificationRequest;
use App\Http\Requests\Notifications\CreateCampaignRequest;
use App\Http\Requests\Notifications\CreateRecurringCampaignRequest;
use App\Http\Requests\Notifications\SendBulkNotificationRequest;
use App\Http\Requests\Notifications\SendNotificationRequest;
use App\Http\Requests\Notifications\SendProjectPushRequest;
use App\Http\Resources\NotificationCampaignResource;
use App\Http\Resources\NotificationLogResource;
use App\Models\NotificationCampaign;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\NotificationManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    use ResolvesPulseProject;

    public function __construct(
        private readonly NotificationManager $notificationManager,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = NotificationLog::query()
            ->where('project_key', $this->pulseProjectKey($request))
            ->with('user')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->string('channel')->value()))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return NotificationLogResource::collection($logs);
    }

    public function show(Request $request, NotificationLog $notification): NotificationLogResource
    {
        abort_unless($notification->project_key === $this->pulseProjectKey($request), 404);

        $notification->load('user');

        return NotificationLogResource::make($notification);
    }

    public function send(SendNotificationRequest $request): JsonResponse
    {
        $projectKey = $this->pulseProjectKey($request);

        $channels = collect($request->input('channels'))
            ->map(fn (string $channel) => NotificationChannelType::from($channel))
            ->all();

        $priority = $request->filled('priority')
            ? NotificationPriority::from($request->string('priority')->value())
            : NotificationPriority::Normal;

        $scheduledAt = $request->date('scheduled_at');

        if ($request->filled('phone') && ! $request->filled('user_id')) {
            $logs = $this->notificationManager->sendSmsToPhone(
                phone: $request->string('phone')->value(),
                title: $request->string('title')->value(),
                body: $request->string('body')->value(),
                data: $request->input('data', []),
                priority: $priority,
                scheduledAt: $scheduledAt,
                projectKey: $projectKey,
            );

            return response()->json([
                'message' => 'Notification queued successfully.',
                'notifications' => NotificationLogResource::collection($logs),
            ], 202);
        }

        $user = User::query()->findOrFail($request->integer('user_id'));

        $logs = $this->notificationManager->sendToUser(
            user: $user,
            title: $request->string('title')->value(),
            body: $request->string('body')->value(),
            channels: $channels,
            data: $request->input('data', []),
            priority: $priority,
            scheduledAt: $scheduledAt,
            projectKey: $projectKey,
        );

        if ($logs->isEmpty()) {
            return response()->json([
                'message' => 'No notification was queued. Configure the selected channel and make sure the recipient can receive it.',
            ], 422);
        }

        $logs->each->refresh();

        return response()->json([
            'message' => 'Notification queued successfully.',
            'notifications' => NotificationLogResource::collection($logs),
        ], 202);
    }

    public function sendBulk(SendBulkNotificationRequest $request): JsonResponse
    {
        $channels = collect($request->input('channels'))
            ->map(fn (string $channel) => NotificationChannelType::from($channel))
            ->all();

        $priority = $request->filled('priority')
            ? NotificationPriority::from($request->string('priority')->value())
            : NotificationPriority::Normal;

        $campaign = $this->notificationManager->sendToUsers(
            userIds: $request->input('user_ids'),
            title: $request->string('title')->value(),
            body: $request->string('body')->value(),
            channels: $channels,
            data: $request->input('data', []),
            priority: $priority,
            createdBy: $request->user()->id,
            scheduledAt: $request->date('scheduled_at'),
            projectKey: $this->pulseProjectKey($request),
        );

        return response()->json([
            'message' => 'Bulk notification campaign created.',
            'campaign' => NotificationCampaignResource::make($campaign),
        ], 202);
    }

    public function broadcast(BroadcastNotificationRequest $request): JsonResponse
    {
        $channels = collect($request->input('channels'))
            ->map(fn (string $channel) => NotificationChannelType::from($channel))
            ->all();

        $priority = $request->filled('priority')
            ? NotificationPriority::from($request->string('priority')->value())
            : NotificationPriority::Normal;

        $campaign = $this->notificationManager->broadcast(
            title: $request->string('title')->value(),
            body: $request->string('body')->value(),
            channels: $channels,
            data: $request->input('data', []),
            priority: $priority,
            createdBy: $request->user()->id,
            scheduledAt: $request->date('scheduled_at'),
            projectKey: $this->pulseProjectKey($request),
        );

        return response()->json([
            'message' => 'Broadcast campaign created.',
            'campaign' => NotificationCampaignResource::make($campaign),
        ], 202);
    }

    public function sendProjectPush(SendProjectPushRequest $request): JsonResponse
    {
        $priority = $request->filled('priority')
            ? NotificationPriority::from($request->string('priority')->value())
            : NotificationPriority::Normal;

        $target = $request->string('target')->value();

        $campaign = $this->notificationManager->sendProjectPush(
            projectKey: $this->pulseProjectKey($request),
            title: $request->string('title')->value(),
            body: $request->string('body')->value(),
            externalUserIds: $target === 'users' ? $request->input('external_user_ids') : null,
            deviceIds: $target === 'devices' ? $request->input('device_ids') : null,
            data: $request->input('data', []),
            priority: $priority,
            scheduledAt: $request->date('scheduled_at'),
            createdBy: $request->user()->id,
        );

        return response()->json([
            'message' => 'Project push campaign queued.',
            'campaign' => NotificationCampaignResource::make($campaign),
        ], 202);
    }

    public function createCampaign(CreateCampaignRequest $request): JsonResponse
    {
        $priority = $request->filled('priority')
            ? NotificationPriority::from($request->string('priority')->value())
            : NotificationPriority::Normal;

        $target = $request->string('target')->value();
        $scheduleType = $request->string('schedule_type')->value();

        $campaign = $this->notificationManager->createScheduledCampaign(
            projectKey: $this->pulseProjectKey($request),
            title: $request->string('title')->value(),
            body: $request->string('body')->value(),
            scheduleType: $scheduleType,
            scheduleTimes: $request->input('schedule_times'),
            externalUserIds: $target === 'users' ? $request->input('external_user_ids') : null,
            data: $request->input('data', []),
            priority: $priority,
            createdBy: $request->user()->id,
            imageUrl: $request->input('image_url'),
            scheduledAt: $request->date('scheduled_at'),
            startsOn: $request->input('starts_on'),
            endsOn: $request->input('ends_on'),
            dates: $request->input('dates'),
            weekdays: $request->input('weekdays'),
        );

        return response()->json([
            'message' => 'Campaign created.',
            'campaign' => NotificationCampaignResource::make($campaign),
        ], 201);
    }

    public function createRecurring(CreateRecurringCampaignRequest $request): JsonResponse
    {
        $priority = $request->filled('priority')
            ? NotificationPriority::from($request->string('priority')->value())
            : NotificationPriority::Normal;

        $target = $request->string('target')->value();

        $campaign = $this->notificationManager->createRecurringCampaign(
            projectKey: $this->pulseProjectKey($request),
            title: $request->string('title')->value(),
            body: $request->string('body')->value(),
            scheduleTimes: $request->input('schedule_times'),
            externalUserIds: $target === 'users' ? $request->input('external_user_ids') : null,
            data: $request->input('data', []),
            priority: $priority,
            createdBy: $request->user()->id,
            imageUrl: $request->input('image_url'),
            startsOn: $request->input('starts_on'),
            endsOn: $request->input('ends_on'),
        );

        return response()->json([
            'message' => 'Recurring campaign created.',
            'campaign' => NotificationCampaignResource::make($campaign),
        ], 201);
    }

    public function markAsRead(Request $request, NotificationLog $notification): NotificationLogResource
    {
        abort_unless($notification->project_key === $this->pulseProjectKey($request), 404);

        $this->notificationManager->markAsRead($notification);

        return NotificationLogResource::make($notification->fresh());
    }

    public function campaigns(Request $request): AnonymousResourceCollection
    {
        $campaigns = NotificationCampaign::query()
            ->where('project_key', $this->pulseProjectKey($request))
            ->with('creator')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when(
                $request->has('is_recurring'),
                fn ($query) => $query->where('is_recurring', $request->boolean('is_recurring')),
            )
            ->when(
                $request->boolean('real_only'),
                fn ($query) => $query->whereNotNull('recurrence'),
            )
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return NotificationCampaignResource::collection($campaigns);
    }

    public function showCampaign(Request $request, NotificationCampaign $campaign): NotificationCampaignResource
    {
        abort_unless($campaign->project_key === $this->pulseProjectKey($request), 404);

        $campaign->load('creator');

        return NotificationCampaignResource::make($campaign);
    }

    public function cancelCampaign(Request $request, NotificationCampaign $campaign): JsonResponse
    {
        abort_unless($campaign->project_key === $this->pulseProjectKey($request), 404);

        $this->notificationManager->cancelCampaign($campaign);

        return response()->json([
            'message' => 'Campaign cancelled successfully.',
            'campaign' => NotificationCampaignResource::make($campaign->fresh()),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $projectKey = $this->pulseProjectKey($request);

        return response()->json([
            'notifications' => [
                'total' => NotificationLog::query()->where('project_key', $projectKey)->count(),
                'sent' => NotificationLog::query()->where('project_key', $projectKey)->where('status', NotificationStatus::Sent)->count(),
                'failed' => NotificationLog::query()->where('project_key', $projectKey)->where('status', NotificationStatus::Failed)->count(),
                'pending' => NotificationLog::query()->where('project_key', $projectKey)->where('status', NotificationStatus::Pending)->count(),
                'scheduled' => NotificationLog::query()->where('project_key', $projectKey)->where('status', NotificationStatus::Scheduled)->count(),
            ],
            'campaigns' => [
                'total' => NotificationCampaign::query()->where('project_key', $projectKey)->count(),
                'active' => NotificationCampaign::query()
                    ->where('project_key', $projectKey)
                    ->whereIn('status', [
                        NotificationStatus::Pending,
                        NotificationStatus::Processing,
                        NotificationStatus::Scheduled,
                        NotificationStatus::Active,
                    ])
                    ->count(),
            ],
            'channels' => NotificationLog::query()
                ->where('project_key', $projectKey)
                ->selectRaw('channel, count(*) as total')
                ->groupBy('channel')
                ->pluck('total', 'channel'),
        ]);
    }
}
