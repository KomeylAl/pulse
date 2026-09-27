<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailContact extends Model
{
    protected $fillable = [
        'project_key',
        'email',
        'name',
        'external_user_id',
        'is_active',
        'unsubscribed_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'unsubscribed_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
