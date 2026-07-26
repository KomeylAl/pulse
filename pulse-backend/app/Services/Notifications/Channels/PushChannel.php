<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\Notifications\NotificationChannelInterface;
use App\DTOs\Notifications\NotificationPayload;
use App\Models\DeviceToken;
use App\Models\NotificationLog;
use App\Services\Notifications\Fcm\FcmService;
use Illuminate\Support\Collection;
use RuntimeException;

class PushChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly FcmService $fcmService,
    ) {}

    public function channel(): string
    {
        return 'push';
    }

    public function supports(NotificationPayload $payload): bool
    {
        return $this->resolveTokens($payload)->isNotEmpty();
    }

    public function send(NotificationLog $log, NotificationPayload $payload): void
    {
        $tokens = $this->resolveTokens($payload)->unique('token')->values();

        if ($tokens->isEmpty()) {
            throw new RuntimeException('No active device tokens found for push notification.');
        }

        $errors = [];

        foreach ($tokens as $deviceToken) {
            try {
                $this->fcmService->sendToToken(
                    $deviceToken,
                    $payload->title,
                    $payload->body,
                    $payload->data,
                );
            } catch (\Throwable $exception) {
                $errors[] = $deviceToken->platform->value.': '.$exception->getMessage();
            }
        }

        if (count($errors) === $tokens->count()) {
            throw new RuntimeException(implode(' | ', $errors));
        }

        $log->update([
            'recipient' => $tokens->pluck('platform')->map->value->unique()->implode(', '),
            'data' => array_merge($payload->data, ['partial_errors' => $errors]),
        ]);
    }

    /**
     * @return Collection<int, DeviceToken>
     */
    private function resolveTokens(NotificationPayload $payload): Collection
    {
        $targetTokens = $payload->data['_target_tokens'] ?? null;

        if (is_array($targetTokens) && $targetTokens !== []) {
            return DeviceToken::query()
                ->where('project_key', $payload->projectKey)
                ->whereIn('token', $targetTokens)
                ->where('is_active', true)
                ->get();
        }

        if ($payload->user !== null) {
            return $payload->user->activeDeviceTokens($payload->projectKey)->get();
        }

        if ($payload->externalUserId !== null) {
            return DeviceToken::query()
                ->where('project_key', $payload->projectKey)
                ->where('external_user_id', $payload->externalUserId)
                ->where('is_active', true)
                ->get();
        }

        return collect();
    }
}
