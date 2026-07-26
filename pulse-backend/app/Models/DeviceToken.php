<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_key',
        'user_id',
        'external_user_id',
        'token',
        'platform',
        'device_name',
        'device_id',
        'is_active',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
