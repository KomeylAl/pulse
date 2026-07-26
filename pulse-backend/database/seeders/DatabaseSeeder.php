<?php

namespace Database\Seeders;

use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@pulse.local',
            'phone' => '09120000000',
            'password' => 'password',
        ]);

        Project::query()->create([
            'user_id' => $admin->id,
            'key' => 'demo',
            'name' => 'Demo Project',
            'type' => ProjectType::Both,
            'api_key' => Project::generateApiKey(),
            'fcm_web_icon' => config('pulse.defaults.fcm_web_icon'),
            'fcm_default_link' => config('pulse.defaults.fcm_default_link'),
        ]);

        User::factory()->count(5)->create();
    }
}
