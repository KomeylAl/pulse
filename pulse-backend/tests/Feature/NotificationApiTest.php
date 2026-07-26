<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_create_project(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ali',
            'email' => 'ali@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ali@example.com');

        Sanctum::actingAs(User::query()->where('email', 'ali@example.com')->firstOrFail());

        $this->postJson('/api/v1/projects', [
            'name' => 'Suicide Prevention',
            'type' => 'pwa',
        ])
            ->assertCreated()
            ->assertJsonPath('project.name', 'Suicide Prevention')
            ->assertJsonStructure(['project' => ['key', 'api_key']]);
    }

    public function test_authenticated_user_can_send_sms_notification(): void
    {
        $user = User::factory()->create(['phone' => '09121111111']);
        $project = Project::factory()->create(['user_id' => $user->id, 'key' => 'app']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/notifications/send', [
            'user_id' => $user->id,
            'title' => 'Test',
            'body' => 'Hello world',
            'channels' => ['sms'],
        ], [
            'X-Pulse-Project' => $project->key,
        ]);

        $response->assertAccepted()
            ->assertJsonPath('message', 'Notification queued successfully.');

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $user->id,
            'channel' => 'sms',
            'title' => 'Test',
            'project_key' => 'app',
        ]);
    }

    public function test_guest_cannot_access_notification_endpoints(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_project_api_key_can_register_device_token(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $owner->id,
            'key' => 'shop',
            'api_key' => 'pk_test_shop_key',
        ]);

        $response = $this->postJson('/api/v1/push/device-tokens', [
            'token' => 'fcm-token-shop-1',
            'platform' => 'pwa',
            'external_user_id' => 'ext-100',
            'device_name' => 'Chrome',
        ], [
            'X-Pulse-Api-Key' => $project->api_key,
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('project_key', 'shop')
            ->assertJsonPath('external_user_id', 'ext-100');

        $this->assertDatabaseHas('device_tokens', [
            'project_key' => 'shop',
            'token' => 'fcm-token-shop-1',
            'external_user_id' => 'ext-100',
            'is_active' => true,
        ]);
    }

    public function test_invalid_project_api_key_is_rejected(): void
    {
        $this->postJson('/api/v1/push/device-tokens', [
            'token' => 'fcm-token',
            'platform' => 'android',
        ], [
            'X-Pulse-Api-Key' => 'wrong-key',
        ])->assertUnauthorized();
    }

    public function test_push_send_queues_log_for_external_user(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $owner->id,
            'key' => 'shop',
            'api_key' => 'pk_test_shop_send',
        ]);

        DeviceToken::query()->create([
            'project_key' => $project->key,
            'token' => 'fcm-token-shop-2',
            'platform' => 'android',
            'external_user_id' => 'ext-200',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/push/send', [
            'title' => 'Order ready',
            'body' => 'Your order is ready',
            'external_user_id' => 'ext-200',
        ], [
            'X-Pulse-Api-Key' => $project->api_key,
        ]);

        $response->assertAccepted();

        $this->assertDatabaseHas('notification_logs', [
            'project_key' => 'shop',
            'external_user_id' => 'ext-200',
            'channel' => 'push',
            'title' => 'Order ready',
        ]);
    }

    public function test_device_tokens_are_isolated_per_project(): void
    {
        $owner = User::factory()->create();
        $app = Project::factory()->create([
            'user_id' => $owner->id,
            'key' => 'app',
            'api_key' => 'pk_test_app',
        ]);
        $shop = Project::factory()->create([
            'user_id' => $owner->id,
            'key' => 'shop',
            'api_key' => 'pk_test_shop',
        ]);

        $this->postJson('/api/v1/push/device-tokens', [
            'token' => 'shared-looking-token',
            'platform' => 'pwa',
            'external_user_id' => 'u1',
        ], [
            'X-Pulse-Api-Key' => $app->api_key,
        ])->assertSuccessful();

        $this->postJson('/api/v1/push/device-tokens', [
            'token' => 'shared-looking-token',
            'platform' => 'pwa',
            'external_user_id' => 'u2',
        ], [
            'X-Pulse-Api-Key' => $shop->api_key,
        ])->assertSuccessful();

        $this->assertSame(2, DeviceToken::query()->where('token', 'shared-looking-token')->count());
    }

    public function test_dashboard_lists_only_owned_projects(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Project::factory()->create(['user_id' => $user->id, 'key' => 'mine']);
        Project::factory()->create(['user_id' => $other->id, 'key' => 'theirs']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_dashboard_scopes_stats_by_project_header(): void
    {
        $user = User::factory()->create();
        Project::factory()->create(['user_id' => $user->id, 'key' => 'app']);
        Project::factory()->create(['user_id' => $user->id, 'key' => 'shop']);
        Sanctum::actingAs($user);

        NotificationLog::query()->create([
            'project_key' => 'app',
            'channel' => 'sms',
            'title' => 'A',
            'body' => 'B',
            'status' => 'sent',
            'priority' => 'normal',
        ]);

        NotificationLog::query()->create([
            'project_key' => 'shop',
            'channel' => 'push',
            'title' => 'C',
            'body' => 'D',
            'status' => 'sent',
            'priority' => 'normal',
        ]);

        $this->getJson('/api/v1/notifications/stats', [
            'X-Pulse-Project' => 'shop',
        ])
            ->assertOk()
            ->assertJsonPath('notifications.total', 1);
    }

    public function test_user_can_save_push_settings(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id, 'key' => 'iot']);
        Sanctum::actingAs($user);

        $credentials = json_encode([
            'type' => 'service_account',
            'project_id' => 'demo',
            'private_key_id' => 'x',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIE\n-----END PRIVATE KEY-----\n",
            'client_email' => 'firebase-adminsdk@demo.iam.gserviceaccount.com',
            'client_id' => '1',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);

        $this->putJson("/api/v1/projects/{$project->key}/push-settings", [
            'firebase_credentials' => $credentials,
            'firebase_web_config' => [
                'apiKey' => 'AIza',
                'authDomain' => 'demo.firebaseapp.com',
                'projectId' => 'demo',
                'storageBucket' => 'demo.appspot.com',
                'messagingSenderId' => '123',
                'appId' => '1:123:web:abc',
            ],
            'vapid_key' => 'BPvapid',
        ])
            ->assertOk()
            ->assertJsonPath('has_firebase', true)
            ->assertJsonPath('has_vapid_key', true);
    }
}
