<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Models\EmailContact;
use App\Models\NotificationLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_settings_are_stored_encrypted_and_not_returned(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'key' => 'mail-app',
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/projects/mail-app/email-settings', [
            'resend_api_key' => 're_live_secret_key',
            'email_from_name' => 'آکمه',
            'email_from_address' => 'Hello@notifications.example.com',
            'email_reply_to' => 'support@example.com',
            'resend_webhook_secret' => 'whsec_'.base64_encode('signing-secret'),
        ])
            ->assertOk()
            ->assertJsonPath('has_email', true)
            ->assertJsonPath('has_email_webhook', true)
            ->assertJsonPath('email_from_address', 'hello@notifications.example.com')
            ->assertJsonPath('email_from_name', 'آکمه')
            ->assertJsonMissing(['resend_api_key' => 're_live_secret_key']);

        $project->refresh();
        $this->assertSame('re_live_secret_key', $project->resend_api_key);
        $this->assertSame('support@example.com', $project->email_reply_to);

        $rawKey = DB::table('projects')->where('id', $project->id)->value('resend_api_key');
        $this->assertIsString($rawKey);
        $this->assertStringNotContainsString('re_live_secret_key', $rawKey);

        $this->putJson('/api/v1/projects/mail-app/email-settings', [
            'email_from_name' => 'آکمه',
            'email_from_address' => 'hello@notifications.example.com',
        ])->assertOk();

        $this->assertSame('re_live_secret_key', $project->fresh()->resend_api_key);
    }

    public function test_invalid_resend_key_is_rejected(): void
    {
        $user = User::factory()->create();
        Project::factory()->create([
            'user_id' => $user->id,
            'key' => 'mail-app',
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/projects/mail-app/email-settings', [
            'resend_api_key' => 'not-a-resend-key',
            'email_from_address' => 'hello@example.com',
        ])->assertUnprocessable();
    }

    public function test_api_key_can_register_contact_and_send_email(): void
    {
        Http::fake([
            'api.resend.com/*' => Http::response(['id' => 'email_123'], 200),
        ]);

        $project = $this->configuredProject();

        $this->postJson('/api/v1/email/contacts', [
            'email' => 'Ali@Example.com',
            'name' => 'Ali',
            'external_user_id' => 'user-42',
        ], [
            'X-Pulse-Api-Key' => $project->api_key,
        ])->assertSuccessful()
            ->assertJsonPath('email', 'ali@example.com')
            ->assertJsonPath('external_user_id', 'user-42');

        $this->postJson('/api/v1/email/send', [
            'external_user_id' => 'user-42',
            'title' => 'سلام',
            'body' => 'نامه تست',
        ], [
            'X-Pulse-Api-Key' => $project->api_key,
        ])->assertAccepted()
            ->assertJsonPath('notifications.0.channel', 'email')
            ->assertJsonPath('notifications.0.recipient', 'ali@example.com')
            ->assertJsonPath('notifications.0.provider_message_id', 'email_123')
            ->assertJsonPath('notifications.0.status', 'sent');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.resend.com/emails'
                && $request->header('Authorization')[0] === 'Bearer re_test_key'
                && $request['from'] === 'Pulse <hello@notifications.example.com>'
                && $request['to'] === ['ali@example.com']
                && $request['subject'] === 'سلام'
                && str_starts_with($request->header('Idempotency-Key')[0], 'pulse-email-');
        });
    }

    public function test_send_without_email_configuration_is_rejected(): void
    {
        $project = Project::factory()->create([
            'key' => 'mail-app',
            'api_key' => 'pk_mail',
        ]);

        $this->postJson('/api/v1/email/send', [
            'to' => ['user@example.com'],
            'title' => 'Hi',
            'body' => 'There',
        ], [
            'X-Pulse-Api-Key' => $project->api_key,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Email channel is not configured for this project.');
    }

    public function test_suppressed_contact_is_not_emailed(): void
    {
        Http::fake();

        $project = $this->configuredProject();

        $this->postJson('/api/v1/email/contacts', [
            'email' => 'gone@example.com',
        ], [
            'X-Pulse-Api-Key' => $project->api_key,
        ])->assertSuccessful();

        $this->deleteJson('/api/v1/email/contacts', [
            'email' => 'gone@example.com',
        ], [
            'X-Pulse-Api-Key' => $project->api_key,
        ])->assertOk();

        $this->postJson('/api/v1/email/send', [
            'to' => ['gone@example.com'],
            'title' => 'Hi',
            'body' => 'There',
        ], [
            'X-Pulse-Api-Key' => $project->api_key,
        ])->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_dashboard_can_send_test_email_and_surface_resend_errors(): void
    {
        Http::fake([
            'api.resend.com/*' => Http::sequence()
                ->push(['message' => 'The domain is not verified.'], 403)
                ->push(['id' => 'email_ok'], 200),
        ]);

        $user = User::factory()->create();
        $project = $this->configuredProject($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/projects/mail-app/email/test', [
            'to' => 'delivered@resend.dev',
            'subject' => 'تست',
            'body' => 'بدنه',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Resend: The domain is not verified.');

        $this->assertDatabaseHas('notification_logs', [
            'project_key' => 'mail-app',
            'channel' => 'email',
            'recipient' => 'delivered@resend.dev',
            'status' => NotificationStatus::Failed->value,
        ]);

        $this->postJson('/api/v1/projects/mail-app/email/test', [
            'to' => 'delivered@resend.dev',
        ])->assertOk()
            ->assertJsonPath('provider_message_id', 'email_ok');
    }

    public function test_dashboard_user_email_channel_uses_account_email(): void
    {
        Http::fake([
            'api.resend.com/*' => Http::response(['id' => 'email_user'], 200),
        ]);

        $user = User::factory()->create(['email' => 'owner@example.com']);
        $project = $this->configuredProject($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/send', [
            'user_id' => $user->id,
            'title' => 'خبر',
            'body' => 'متن',
            'channels' => ['email'],
        ], [
            'X-Pulse-Project' => $project->key,
        ])->assertAccepted()
            ->assertJsonPath('notifications.0.recipient', 'owner@example.com')
            ->assertJsonPath('notifications.0.provider_message_id', 'email_user');
    }

    public function test_webhook_updates_delivery_and_suppresses_bounced_contacts(): void
    {
        $project = $this->configuredProject();
        $secret = 'whsec_'.base64_encode(random_bytes(24));
        $project->update(['resend_webhook_secret' => $secret]);

        EmailContact::query()->create([
            'project_key' => $project->key,
            'email' => 'person@example.com',
            'is_active' => true,
        ]);

        $log = NotificationLog::query()->create([
            'project_key' => $project->key,
            'channel' => 'email',
            'title' => 'Hi',
            'body' => 'There',
            'status' => NotificationStatus::Sent,
            'recipient' => 'person@example.com',
            'provider_message_id' => 'email_bounce_1',
            'delivery_status' => 'sent',
        ]);

        $payload = json_encode([
            'type' => 'email.bounced',
            'data' => [
                'email_id' => 'email_bounce_1',
                'to' => ['person@example.com'],
                'tags' => [
                    ['name' => 'log_id', 'value' => (string) $log->id],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/v1/email/webhook/mail-app',
            [],
            [],
            [],
            $this->webhookServer($secret, $payload, (string) time()),
            $payload,
        )->assertUnauthorized();

        $timestamp = (string) time();
        $this->call(
            'POST',
            '/api/v1/email/webhook/mail-app',
            [],
            [],
            [],
            $this->webhookServer($secret, $payload, $timestamp, valid: true),
            $payload,
        )->assertOk()
            ->assertJsonPath('message', 'Webhook applied.');

        $log->refresh();
        $this->assertSame(NotificationStatus::Failed, $log->status);
        $this->assertSame('bounced', $log->delivery_status);

        $this->assertDatabaseHas('email_contacts', [
            'project_key' => 'mail-app',
            'email' => 'person@example.com',
            'is_active' => false,
        ]);
    }

    public function test_integration_guide_includes_email_steps(): void
    {
        $user = User::factory()->create();
        $project = $this->configuredProject($user);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects/'.$project->key.'/integration-guide')
            ->assertOk()
            ->assertJsonPath('email.steps.0.title', 'ساخت اکانت در Resend')
            ->assertJsonStructure([
                'email' => [
                    'webhook_url',
                    'steps',
                    'samples' => ['register_contact_curl', 'send_email_curl'],
                ],
            ]);
    }

    private function configuredProject(?User $owner = null): Project
    {
        return Project::factory()->create([
            'user_id' => $owner?->id ?? User::factory(),
            'key' => 'mail-app',
            'api_key' => 'pk_mail_app',
            'resend_api_key' => 're_test_key',
            'email_from_name' => 'Pulse',
            'email_from_address' => 'hello@notifications.example.com',
            'email_reply_to' => 'reply@notifications.example.com',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function webhookServer(string $secret, string $payload, string $timestamp, bool $valid = false): array
    {
        $id = 'msg_test';
        $signature = 'v1,invalid';

        if ($valid) {
            $key = base64_decode(substr($secret, strlen('whsec_')), true);
            $signature = 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$payload, $key, true));
        }

        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_SVIX_ID' => $id,
            'HTTP_SVIX_TIMESTAMP' => $timestamp,
            'HTTP_SVIX_SIGNATURE' => $signature,
        ];
    }
}
