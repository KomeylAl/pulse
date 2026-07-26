<?php

namespace App\Models;

use App\Enums\DevicePlatform;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function activeDeviceTokens(?string $projectKey = null): HasMany
    {
        return $this->deviceTokens()
            ->where('is_active', true)
            ->when($projectKey !== null, fn ($query) => $query->where('project_key', $projectKey));
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function activeTokensForPlatform(DevicePlatform $platform): HasMany
    {
        return $this->activeDeviceTokens()->where('platform', $platform);
    }
}
