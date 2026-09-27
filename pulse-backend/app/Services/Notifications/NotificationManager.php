<?php

namespace App\Services\Notifications;

use App\DTOs\Notifications\NotificationPayload;
use App\Enums\NotificationChannelType;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Enums\NotificationTargetType;
use App\Jobs\ProcessNotificationCampaignJob;
use App\Jobs\SendNotificationJob;
use App\Models\DeviceToken;
use App\Models\EmailContact;
use App\Models\NotificationCampaign;
use App\Models\NotificationLog;
use App\Models\User;
use App\Support\PulseProjectRegistry;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

class NotificationManager
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly PulseProjectRegistry $projects,
    ) {}

    /**
     * @param  array<NotificationChannelType>  $channels
     * @param  array<string, mixed>  $data
     */
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $channels,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?CarbonInterface $scheduledAt = null,
        ?string $projectKey = null,
    ): Collection {
        $projectKey ??= throw new RuntimeException('project_key is required.');

        $payload = new NotificationPayload(
            title: $title,
            body: $body,
            channels: $channels,
            data: $data,
            priority: $priority,
            user: $user,
            phone: $user->phone,
            projectKey: $projectKey,
        );

        return $this->createLogsForPayload($payload, null, $scheduledAt);
    }

    /**
     * @param  array<int>  $userIds
     * @param  array<NotificationChannelType>  $channels
     * @param  array<string, mixed>  $data
     */
    public function sendToUsers(
        array $userIds,
        string $title,
        string $body,
        array $channels,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?int $createdBy = null,
        ?CarbonInterface $scheduledAt = null,
        ?string $projectKey = null,
    ): NotificationCampaign {
        return $this->createCampaign(
            targetType: NotificationTargetType::Users,
            userIds: $userIds,
            title: $title,
            body: $body,
            channels: $channels,
            data: $data,
            priority: $priority,
            createdBy: $createdBy,
            scheduledAt: $scheduledAt,
            projectKey: $projectKey ?? throw new RuntimeException('project_key is required.'),
        );
    }

    /**
     * @param  array<NotificationChannelType>  $channels
     * @param  array<string, mixed>  $data
     */
    public function broadcast(
        string $title,
        string $body,
        array $channels,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?int $createdBy = null,
        ?CarbonInterface $scheduledAt = null,
        ?string $projectKey = null,
    ): NotificationCampaign {
        return $this->createCampaign(
            targetType: NotificationTargetType::Broadcast,
            userIds: null,
            title: $title,
            body: $body,
            channels: $channels,
            data: $data,
            priority: $priority,
            createdBy: $createdBy,
            scheduledAt: $scheduledAt,
            projectKey: $projectKey ?? throw new RuntimeException('project_key is required.'),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function sendSmsToPhone(
        string $phone,
        string $title,
        string $body,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?CarbonInterface $scheduledAt = null,
        ?string $projectKey = null,
    ): Collection {
        $payload = new NotificationPayload(
            title: $title,
            body: $body,
            channels: [NotificationChannelType::Sms],
            data: $data,
            priority: $priority,
            phone: $phone,
            projectKey: $projectKey ?? throw new RuntimeException('project_key is required.'),
        );

        return $this->createLogsForPayload($payload, null, $scheduledAt);
    }

    /**
     * Send push to devices registered under an external app user id.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendPushToExternalUser(
        string $projectKey,
        string $externalUserId,
        string $title,
        string $body,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?CarbonInterface $scheduledAt = null,
    ): Collection {
        $this->projects->findOrFail($projectKey);

        $payload = new NotificationPayload(
            title: $title,
            body: $body,
            channels: [NotificationChannelType::Push],
            data: $data,
            priority: $priority,
            projectKey: $projectKey,
            externalUserId: $externalUserId,
        );

        return $this->createLogsForPayload($payload, null, $scheduledAt);
    }

    /**
     * Send one email per address. Inactive contacts are skipped.
     *
     * @param  list<string>  $addresses
     * @param  array<string, mixed>  $data
     */
    public function sendEmail(
        string $projectKey,
        array $addresses,
        string $title,
        string $body,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?CarbonInterface $scheduledAt = null,
        ?string $externalUserId = null,
        ?string $replyTo = null,
    ): Collection {
        $project = $this->projects->findModelOrFail($projectKey);

        if (! $project->hasEmailChannel()) {
            throw new RuntimeException('Email channel is not configured for this project.');
        }

        $addresses = collect($addresses)
            ->map(fn (string $email) => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values();

        if ($addresses->isEmpty()) {
            throw new RuntimeException('No email recipients were provided.');
        }

        $suppressed = EmailContact::query()
            ->where('project_key', $projectKey)
            ->whereIn('email', $addresses->all())
            ->where('is_active', false)
            ->pluck('email');

        $addresses = $addresses
            ->reject(fn (string $email) => $suppressed->contains($email))
            ->values();

        if ($addresses->isEmpty()) {
            throw new RuntimeException('All recipients are unsubscribed or suppressed.');
        }

        $extra = $data;

        if (filled($replyTo)) {
            $extra['reply_to'] = $replyTo;
        }

        $logs = collect();

        foreach ($addresses as $address) {
            $payload = new NotificationPayload(
                title: $title,
                body: $body,
                channels: [NotificationChannelType::Email],
                data: $extra,
                priority: $priority,
                projectKey: $projectKey,
                externalUserId: $externalUserId,
                email: $address,
            );

            $logs = $logs->merge($this->createLogsForPayload($payload, null, $scheduledAt));
        }

        return $logs;
    }

    /**
     * Send to every active email saved for an external app user.
     *
     * @param  array<string, mixed>  $data
     */
    public function sendEmailToExternalUser(
        string $projectKey,
        string $externalUserId,
        string $title,
        string $body,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?CarbonInterface $scheduledAt = null,
        ?string $replyTo = null,
    ): Collection {
        $addresses = EmailContact::query()
            ->where('project_key', $projectKey)
            ->where('external_user_id', $externalUserId)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('email')
            ->all();

        if ($addresses === []) {
            throw new RuntimeException('No active email contact found for this user.');
        }

        return $this->sendEmail(
            projectKey: $projectKey,
            addresses: $addresses,
            title: $title,
            body: $body,
            data: $data,
            priority: $priority,
            scheduledAt: $scheduledAt,
            externalUserId: $externalUserId,
            replyTo: $replyTo,
        );
    }

    /**
     * Send push directly to one or more FCM tokens within a project.
     *
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $data
     */
    public function sendPushToTokens(
        string $projectKey,
        array $tokens,
        string $title,
        string $body,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?CarbonInterface $scheduledAt = null,
    ): Collection {
        $this->projects->findOrFail($projectKey);

        $deviceTokens = DeviceToken::query()
            ->where('project_key', $projectKey)
            ->whereIn('token', $tokens)
            ->where('is_active', true)
            ->get();

        if ($deviceTokens->isEmpty()) {
            throw new RuntimeException('No active device tokens matched the provided tokens for this project.');
        }

        $logs = collect();

        foreach ($deviceTokens->groupBy(fn (DeviceToken $token) => $token->external_user_id ?? 'token:'.$token->id) as $group) {
            /** @var DeviceToken $first */
            $first = $group->first();
            $preferred = $group->sortByDesc(fn (DeviceToken $token) => $token->last_used_at ?? $token->created_at)->take(1);

            $payload = new NotificationPayload(
                title: $title,
                body: $body,
                channels: [NotificationChannelType::Push],
                data: array_merge($data, [
                    '_target_tokens' => $preferred->pluck('token')->values()->all(),
                ]),
                priority: $priority,
                user: $first->user_id ? User::query()->find($first->user_id) : null,
                projectKey: $projectKey,
                externalUserId: $first->external_user_id,
            );

            $logs = $logs->merge($this->createLogsForPayload($payload, null, $scheduledAt));
        }

        return $logs;
    }

    /**
     * Instant / one-shot project push to all devices or selected external users.
     *
     * @param  list<string>|null  $externalUserIds
     * @param  list<int>|null  $deviceIds
     * @param  array<string, mixed>  $data
     */
    public function sendProjectPush(
        string $projectKey,
        string $title,
        string $body,
        ?array $externalUserIds = null,
        ?array $deviceIds = null,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?CarbonInterface $scheduledAt = null,
        ?int $createdBy = null,
    ): NotificationCampaign {
        $this->projects->findOrFail($projectKey);

        if ($deviceIds !== null && $deviceIds !== []) {
            return $this->createCampaign(
                targetType: NotificationTargetType::ProjectDevices,
                userIds: null,
                title: $title,
                body: $body,
                channels: [NotificationChannelType::Push],
                data: array_merge($data, ['_device_ids' => array_values($deviceIds)]),
                priority: $priority,
                createdBy: $createdBy,
                scheduledAt: $scheduledAt,
                projectKey: $projectKey,
                externalUserIds: null,
            );
        }

        if ($externalUserIds !== null && $externalUserIds !== []) {
            return $this->createCampaign(
                targetType: NotificationTargetType::ExternalUsers,
                userIds: null,
                title: $title,
                body: $body,
                channels: [NotificationChannelType::Push],
                data: $data,
                priority: $priority,
                createdBy: $createdBy,
                scheduledAt: $scheduledAt,
                projectKey: $projectKey,
                externalUserIds: array_values($externalUserIds),
            );
        }

        return $this->createCampaign(
            targetType: NotificationTargetType::ProjectDevices,
            userIds: null,
            title: $title,
            body: $body,
            channels: [NotificationChannelType::Push],
            data: $data,
            priority: $priority,
            createdBy: $createdBy,
            scheduledAt: $scheduledAt,
            projectKey: $projectKey,
            externalUserIds: null,
        );
    }

    /**
     * Daily recurring push campaign (e.g. 08:00, 12:00, 16:00, 20:00).
     *
     * @param  list<string>  $scheduleTimes  HH:mm in app timezone
     * @param  list<string>|null  $externalUserIds
     * @param  array<string, mixed>  $data
     */
    public function createRecurringCampaign(
        string $projectKey,
        string $title,
        string $body,
        array $scheduleTimes,
        ?array $externalUserIds = null,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?int $createdBy = null,
        ?string $imageUrl = null,
        ?string $startsOn = null,
        ?string $endsOn = null,
    ): NotificationCampaign {
        return $this->createScheduledCampaign(
            projectKey: $projectKey,
            title: $title,
            body: $body,
            scheduleType: 'daily',
            scheduleTimes: $scheduleTimes,
            externalUserIds: $externalUserIds,
            data: $data,
            priority: $priority,
            createdBy: $createdBy,
            imageUrl: $imageUrl,
            startsOn: $startsOn ?? now()->toDateString(),
            endsOn: $endsOn ?? now()->addYear()->toDateString(),
        );
    }

    /**
     * Create a real campaign with one of: once | daily | dates | weekly.
     *
     * @param  list<string>|null  $scheduleTimes
     * @param  list<string>|null  $externalUserIds
     * @param  list<string>|null  $dates  Y-m-d
     * @param  list<int>|null  $weekdays  ISO 1=Mon … 7=Sun
     * @param  array<string, mixed>  $data
     */
    public function createScheduledCampaign(
        string $projectKey,
        string $title,
        string $body,
        string $scheduleType,
        ?array $scheduleTimes = null,
        ?array $externalUserIds = null,
        array $data = [],
        NotificationPriority $priority = NotificationPriority::Normal,
        ?int $createdBy = null,
        ?string $imageUrl = null,
        ?CarbonInterface $scheduledAt = null,
        ?string $startsOn = null,
        ?string $endsOn = null,
        ?array $dates = null,
        ?array $weekdays = null,
    ): NotificationCampaign {
        $this->projects->findOrFail($projectKey);

        $targetType = ($externalUserIds !== null && $externalUserIds !== [])
            ? NotificationTargetType::ExternalUsers
            : NotificationTargetType::ProjectDevices;

        $payloadData = $data;
        if (filled($imageUrl)) {
            $payloadData['icon'] = $imageUrl;
            $payloadData['image'] = $imageUrl;
        }

        if ($scheduleType === 'once') {
            if ($scheduledAt === null) {
                throw new RuntimeException('scheduled_at is required for once campaigns.');
            }

            $campaign = NotificationCampaign::query()->create([
                'project_key' => $projectKey,
                'title' => $title,
                'body' => $body,
                'image_url' => $imageUrl,
                'channels' => [NotificationChannelType::Push->value],
                'target_type' => $targetType,
                'target_user_ids' => null,
                'target_external_user_ids' => $externalUserIds,
                'data' => $payloadData,
                'status' => $scheduledAt->isFuture()
                    ? NotificationStatus::Scheduled
                    : NotificationStatus::Pending,
                'priority' => $priority,
                'scheduled_at' => $scheduledAt,
                'recurrence' => 'once',
                'schedule_times' => null,
                'schedule_config' => null,
                'is_recurring' => false,
                'created_by' => $createdBy,
            ]);

            if ($campaign->status === NotificationStatus::Pending) {
                ProcessNotificationCampaignJob::dispatch($campaign->id);
            }

            return $campaign;
        }

        $times = collect($scheduleTimes ?? [])
            ->map(fn (string $time) => substr(trim($time), 0, 5))
            ->filter(fn (string $time) => (bool) preg_match('/^\d{2}:\d{2}$/', $time))
            ->unique()
            ->values()
            ->all();

        if ($times === []) {
            throw new RuntimeException('At least one valid schedule time (HH:mm) is required.');
        }

        $config = match ($scheduleType) {
            'daily' => [
                'starts_on' => $startsOn ?? now()->toDateString(),
                'ends_on' => $endsOn ?? now()->addYear()->toDateString(),
            ],
            'dates' => [
                'dates' => collect($dates ?? [])
                    ->map(fn (string $date) => substr(trim($date), 0, 10))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
            ],
            'weekly' => [
                'starts_on' => $startsOn ?? now()->toDateString(),
                'ends_on' => $endsOn ?? now()->addYear()->toDateString(),
                'weekdays' => collect($weekdays ?? [])
                    ->map(fn ($day) => (int) $day)
                    ->filter(fn (int $day) => $day >= 1 && $day <= 7)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
            ],
            default => throw new RuntimeException("Unsupported schedule type [{$scheduleType}]."),
        };

        if ($scheduleType === 'dates' && ($config['dates'] ?? []) === []) {
            throw new RuntimeException('At least one date is required for dates campaigns.');
        }

        if ($scheduleType === 'weekly' && ($config['weekdays'] ?? []) === []) {
            throw new RuntimeException('At least one weekday is required for weekly campaigns.');
        }

        $campaign = NotificationCampaign::query()->create([
            'project_key' => $projectKey,
            'title' => $title,
            'body' => $body,
            'image_url' => $imageUrl,
            'channels' => [NotificationChannelType::Push->value],
            'target_type' => $targetType,
            'target_user_ids' => null,
            'target_external_user_ids' => $externalUserIds,
            'data' => $payloadData,
            'status' => NotificationStatus::Active,
            'priority' => $priority,
            'scheduled_at' => null,
            'recurrence' => $scheduleType,
            'schedule_times' => $times,
            'schedule_config' => $config,
            'recurrence_meta' => ['fired' => []],
            'is_recurring' => true,
            'created_by' => $createdBy,
        ]);

        return $campaign;
    }

    public function shouldFireRecurringSlot(
        NotificationCampaign $campaign,
        CarbonInterface $now,
        string $slot,
    ): bool {
        if (! $campaign->is_recurring || $campaign->status !== NotificationStatus::Active) {
            return false;
        }

        $times = $campaign->schedule_times ?? [];
        if (! in_array($slot, $times, true)) {
            return false;
        }

        $config = $campaign->schedule_config ?? [];
        $today = $now->toDateString();

        return match ($campaign->recurrence) {
            'daily' => $this->isDateInRange($today, $config['starts_on'] ?? null, $config['ends_on'] ?? null),
            'dates' => in_array($today, $config['dates'] ?? [], true),
            'weekly' => $this->isDateInRange($today, $config['starts_on'] ?? null, $config['ends_on'] ?? null)
                && in_array((int) $now->isoWeekday(), array_map('intval', $config['weekdays'] ?? []), true),
            default => $campaign->recurrence === 'daily',
        };
    }

    public function isRecurringCampaignExpired(
        NotificationCampaign $campaign,
        CarbonInterface $now,
    ): bool {
        $config = $campaign->schedule_config ?? [];
        $today = $now->toDateString();

        return match ($campaign->recurrence) {
            'daily', 'weekly' => filled($config['ends_on'] ?? null) && $today > $config['ends_on'],
            'dates' => filled($config['dates'] ?? null)
                && $today > (collect($config['dates'])->sort()->last() ?? $today),
            default => false,
        };
    }

    private function isDateInRange(string $today, ?string $startsOn, ?string $endsOn): bool
    {
        if (filled($startsOn) && $today < $startsOn) {
            return false;
        }

        if (filled($endsOn) && $today > $endsOn) {
            return false;
        }

        return true;
    }

    /**
     * Fire one slot of a recurring campaign (creates recipient logs and sends).
     */
    public function runRecurringSlot(NotificationCampaign $campaign, string $slot): void
    {
        if (! $campaign->is_recurring || $campaign->status !== NotificationStatus::Active) {
            return;
        }

        $today = now()->toDateString();
        $meta = $campaign->recurrence_meta ?? ['fired' => []];
        $firedToday = $meta['fired'][$today] ?? [];

        if (in_array($slot, $firedToday, true)) {
            return;
        }

        $this->dispatchProjectCampaignRecipients($campaign, dispatchNow: true);

        $firedToday[] = $slot;
        $meta['fired'][$today] = $firedToday;
        // Keep only last 7 days of meta to avoid unbounded growth.
        $meta['fired'] = collect($meta['fired'])
            ->sortKeysDesc()
            ->take(7)
            ->all();

        $campaign->update([
            'recurrence_meta' => $meta,
            'processed_at' => now(),
            'sent_count' => $campaign->logs()->where('status', NotificationStatus::Sent)->count(),
            'failed_count' => $campaign->logs()->where('status', NotificationStatus::Failed)->count(),
            'total_recipients' => $campaign->logs()->count(),
        ]);
    }

    public function processCampaign(NotificationCampaign $campaign): void
    {
        if ($campaign->status === NotificationStatus::Cancelled) {
            return;
        }

        if ($campaign->is_recurring) {
            return;
        }

        $campaign->update(['status' => NotificationStatus::Processing]);

        $this->dispatchProjectCampaignRecipients($campaign, dispatchNow: true);

        $campaign->refresh();
        $campaign->update([
            'status' => NotificationStatus::Sent,
            'processed_at' => now(),
            'sent_count' => $campaign->logs()->where('status', NotificationStatus::Sent)->count(),
            'failed_count' => $campaign->logs()->where('status', NotificationStatus::Failed)->count(),
        ]);
    }

    public function cancelCampaign(NotificationCampaign $campaign): void
    {
        if (in_array($campaign->status, [NotificationStatus::Sent, NotificationStatus::Cancelled], true)) {
            throw new RuntimeException('This campaign cannot be cancelled.');
        }

        $campaign->update(['status' => NotificationStatus::Cancelled]);

        $campaign->logs()
            ->whereIn('status', [NotificationStatus::Pending, NotificationStatus::Scheduled])
            ->update(['status' => NotificationStatus::Cancelled]);
    }

    public function markAsRead(NotificationLog $log): void
    {
        $log->update(['read_at' => now()]);
    }

    private function dispatchProjectCampaignRecipients(NotificationCampaign $campaign, bool $dispatchNow = false): void
    {
        $channels = collect($campaign->channels)
            ->map(fn (string $channel) => NotificationChannelType::from($channel))
            ->all();

        if (in_array($campaign->target_type, [
            NotificationTargetType::ProjectDevices,
            NotificationTargetType::ExternalUsers,
        ], true)) {
            $query = DeviceToken::query()
                ->where('project_key', $campaign->project_key)
                ->where('is_active', true);

            $deviceIds = $campaign->data['_device_ids'] ?? null;
            if (is_array($deviceIds) && $deviceIds !== []) {
                $query->whereIn('id', $deviceIds);
            } elseif ($campaign->target_type === NotificationTargetType::ExternalUsers) {
                $ids = $campaign->target_external_user_ids ?? [];
                $query->whereIn('external_user_id', $ids);
            }

            $tokens = $query->get()->unique('token')->values();
            $campaign->update(['total_recipients' => $tokens->groupBy(
                fn (DeviceToken $token) => $token->external_user_id ?? 'token:'.$token->id
            )->count()]);

            foreach ($tokens->groupBy(fn (DeviceToken $token) => $token->external_user_id ?? 'token:'.$token->id) as $group) {
                /** @var DeviceToken $first */
                $first = $group->first();

                // Prefer the most recently used token for each subscriber to avoid multi-push.
                $preferred = $group->sortByDesc(fn (DeviceToken $token) => $token->last_used_at ?? $token->created_at)->take(1);

                $payload = new NotificationPayload(
                    title: $campaign->title,
                    body: $campaign->body,
                    channels: $channels,
                    data: array_merge($campaign->data ?? [], [
                        '_target_tokens' => $preferred->pluck('token')->values()->all(),
                    ]),
                    priority: $campaign->priority,
                    user: $first->user_id ? User::query()->find($first->user_id) : null,
                    projectKey: $campaign->project_key,
                    externalUserId: $first->external_user_id,
                );

                $this->createLogsForPayload($payload, $campaign->id, null, dispatchNow: $dispatchNow);
            }

            return;
        }

        $userIds = $this->resolveCampaignUserIds($campaign);
        $campaign->update(['total_recipients' => $userIds->count()]);

        foreach ($userIds as $userId) {
            $user = User::query()->find($userId);

            if ($user === null) {
                continue;
            }

            $payload = new NotificationPayload(
                title: $campaign->title,
                body: $campaign->body,
                channels: $channels,
                data: $campaign->data ?? [],
                priority: $campaign->priority,
                user: $user,
                phone: $user->phone,
                projectKey: $campaign->project_key,
            );

            $this->createLogsForPayload($payload, $campaign->id, $campaign->scheduled_at, dispatchNow: $dispatchNow);
        }
    }

    /**
     * @param  array<NotificationChannelType>  $channels
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $externalUserIds
     */
    private function createCampaign(
        NotificationTargetType $targetType,
        ?array $userIds,
        string $title,
        string $body,
        array $channels,
        array $data,
        NotificationPriority $priority,
        ?int $createdBy,
        ?CarbonInterface $scheduledAt,
        string $projectKey,
        ?array $externalUserIds = null,
        bool $dispatch = true,
    ): NotificationCampaign {
        $this->projects->findOrFail($projectKey);

        $status = $scheduledAt !== null && $scheduledAt->isFuture()
            ? NotificationStatus::Scheduled
            : NotificationStatus::Pending;

        $campaign = NotificationCampaign::query()->create([
            'project_key' => $projectKey,
            'title' => $title,
            'body' => $body,
            'channels' => array_map(fn (NotificationChannelType $channel) => $channel->value, $channels),
            'target_type' => $targetType,
            'target_user_ids' => $userIds,
            'target_external_user_ids' => $externalUserIds,
            'data' => $data,
            'status' => $status,
            'priority' => $priority,
            'scheduled_at' => $scheduledAt,
            'is_recurring' => false,
            'created_by' => $createdBy,
        ]);

        if ($status === NotificationStatus::Scheduled || ! $dispatch) {
            return $campaign;
        }

        ProcessNotificationCampaignJob::dispatch($campaign->id);

        return $campaign;
    }

    /**
     * @return Collection<int, NotificationLog>
     */
    private function createLogsForPayload(
        NotificationPayload $payload,
        ?int $campaignId,
        ?CarbonInterface $scheduledAt,
        bool $dispatchNow = false,
    ): Collection {
        $logs = collect();

        foreach ($payload->channels as $channel) {
            if (! $this->dispatcher->supportsChannel($channel, $payload)) {
                continue;
            }

            $status = $scheduledAt !== null && $scheduledAt->isFuture()
                ? NotificationStatus::Scheduled
                : NotificationStatus::Pending;

            $log = NotificationLog::query()->create([
                'project_key' => $payload->projectKey,
                'campaign_id' => $campaignId,
                'user_id' => $payload->user?->id,
                'external_user_id' => $payload->externalUserId,
                'channel' => $channel,
                'title' => $payload->title,
                'body' => $payload->body,
                'data' => $payload->data,
                'status' => $status,
                'priority' => $payload->priority,
                'recipient' => match ($channel) {
                    NotificationChannelType::Sms => $payload->phone ?? $payload->user?->phone,
                    NotificationChannelType::Email => $payload->email ?? $payload->user?->email,
                    default => null,
                },
                'scheduled_at' => $scheduledAt,
            ]);

            $logs->push($log);

            if ($status === NotificationStatus::Scheduled) {
                continue;
            }

            if ($dispatchNow) {
                SendNotificationJob::dispatchSync($log->id);
            } else {
                SendNotificationJob::dispatch($log->id);
            }
        }

        return $logs;
    }

    /** @return Collection<int, int> */
    private function resolveCampaignUserIds(NotificationCampaign $campaign): Collection
    {
        return match ($campaign->target_type) {
            NotificationTargetType::User, NotificationTargetType::Users => collect($campaign->target_user_ids ?? []),
            NotificationTargetType::Broadcast => User::query()->pluck('id'),
        };
    }
}
