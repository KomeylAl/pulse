<?php

namespace App\Models;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use Concerns\StoresDatesInAppTimezone;
    use HasFactory;

    protected $fillable = [
        'project_key',
        'campaign_id',
        'user_id',
        'external_user_id',
        'channel',
        'title',
        'body',
        'data',
        'status',
        'priority',
        'recipient',
        'provider_message_id',
        'delivery_status',
        'error_message',
        'attempts',
        'scheduled_at',
        'sent_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannelType::class,
            'data' => 'array',
            'status' => NotificationStatus::class,
            'priority' => NotificationPriority::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NotificationCampaign::class, 'campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
