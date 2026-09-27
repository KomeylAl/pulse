<?php

namespace App\Models;

use App\Enums\ProjectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'key',
        'name',
        'type',
        'api_key',
        'firebase_credentials',
        'firebase_web_config',
        'vapid_key',
        'fcm_web_icon',
        'fcm_default_link',
        'resend_api_key',
        'email_from_address',
        'email_from_name',
        'email_reply_to',
        'resend_webhook_secret',
    ];

    protected $hidden = [
        'firebase_credentials',
        'vapid_key',
        'resend_api_key',
        'resend_webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'firebase_credentials' => 'encrypted',
            'firebase_web_config' => 'array',
            'vapid_key' => 'encrypted',
            'resend_api_key' => 'encrypted',
            'resend_webhook_secret' => 'encrypted',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class, 'project_key', 'key');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'project_key', 'key');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(NotificationCampaign::class, 'project_key', 'key');
    }

    public function emailContacts(): HasMany
    {
        return $this->hasMany(EmailContact::class, 'project_key', 'key');
    }

    public function hasFirebaseCredentials(): bool
    {
        return filled($this->firebase_credentials);
    }

    public function hasEmailChannel(): bool
    {
        return filled($this->resend_api_key) && filled($this->email_from_address);
    }

    public static function generateApiKey(): string
    {
        return 'pk_'.Str::lower(Str::random(40));
    }

    public static function generateKeyFromName(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? Str::limit($base, 48, '') : 'project';
        $key = $base;
        $i = 1;

        while (static::query()->where('key', $key)->exists()) {
            $key = Str::limit($base, 40, '').'-'.$i;
            $i++;
        }

        return $key;
    }
}
