<?php

namespace App\Models;

use App\Enums\NotificationPriority;
use App\Enums\NotificationStatus;
use App\Enums\NotificationTargetType;
use App\Models\Concerns\StoresDatesInAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationCampaign extends Model
{
    use HasFactory;
    use StoresDatesInAppTimezone;

    protected $fillable = [
        'project_key',
        'title',
        'body',
        'image_url',
        'channels',
        'target_type',
        'target_user_ids',
        'target_external_user_ids',
        'data',
        'status',
        'priority',
        'scheduled_at',
        'recurrence',
        'schedule_times',
        'schedule_config',
        'recurrence_meta',
        'is_recurring',
        'processed_at',
        'created_by',
        'total_recipients',
        'sent_count',
        'failed_count',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'target_type' => NotificationTargetType::class,
            'target_user_ids' => 'array',
            'target_external_user_ids' => 'array',
            'data' => 'array',
            'status' => NotificationStatus::class,
            'priority' => NotificationPriority::class,
            'scheduled_at' => 'datetime',
            'schedule_times' => 'array',
            'schedule_config' => 'array',
            'recurrence_meta' => 'array',
            'is_recurring' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'campaign_id');
    }
}
