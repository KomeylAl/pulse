<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->text('resend_api_key')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('email_from_name')->nullable();
            $table->string('email_reply_to')->nullable();
            $table->text('resend_webhook_secret')->nullable();
        });

        Schema::create('email_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('project_key', 64);
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('external_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['project_key', 'email']);
            $table->index(['project_key', 'external_user_id']);
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('provider_message_id')->nullable()->after('recipient');
            $table->string('delivery_status', 32)->nullable()->after('provider_message_id');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['provider_message_id']);
            $table->dropColumn(['provider_message_id', 'delivery_status']);
        });

        Schema::dropIfExists('email_contacts');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'resend_api_key',
                'email_from_address',
                'email_from_name',
                'email_reply_to',
                'resend_webhook_secret',
            ]);
        });
    }
};
