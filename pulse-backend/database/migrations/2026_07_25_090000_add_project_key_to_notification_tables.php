<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $defaultProject = (string) config('pulse.default_project', 'app');

        Schema::table('device_tokens', function (Blueprint $table) use ($defaultProject): void {
            $table->string('project_key', 64)->default($defaultProject)->after('id');
            $table->string('external_user_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->index('project_key');
            $table->index(['project_key', 'external_user_id']);
        });

        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'token']);
            $table->unique(['project_key', 'token']);
        });

        Schema::table('notification_logs', function (Blueprint $table) use ($defaultProject): void {
            $table->string('project_key', 64)->default($defaultProject)->after('id');
            $table->string('external_user_id')->nullable()->after('user_id');
            $table->index('project_key');
            $table->index(['project_key', 'external_user_id']);
        });

        Schema::table('notification_campaigns', function (Blueprint $table) use ($defaultProject): void {
            $table->string('project_key', 64)->default($defaultProject)->after('id');
            $table->index('project_key');
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table): void {
            $table->dropIndex(['project_key']);
            $table->dropColumn('project_key');
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropIndex(['project_key']);
            $table->dropIndex(['project_key', 'external_user_id']);
            $table->dropColumn(['project_key', 'external_user_id']);
        });

        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropUnique(['project_key', 'token']);
            $table->dropIndex(['project_key']);
            $table->dropIndex(['project_key', 'external_user_id']);
            $table->dropColumn(['project_key', 'external_user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unique(['user_id', 'token']);
        });
    }
};
