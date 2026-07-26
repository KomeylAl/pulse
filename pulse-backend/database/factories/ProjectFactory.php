<?php

namespace Database\Factories;

use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'user_id' => User::factory(),
            'key' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'name' => Str::title($name),
            'type' => ProjectType::Both,
            'api_key' => Project::generateApiKey(),
            'firebase_credentials' => null,
            'firebase_web_config' => null,
            'vapid_key' => null,
            'fcm_web_icon' => '/icons/icon-192x192.png',
            'fcm_default_link' => 'http://localhost:3000',
        ];
    }
}
