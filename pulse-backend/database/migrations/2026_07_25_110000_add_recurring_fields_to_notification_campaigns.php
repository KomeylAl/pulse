<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table): void {
            $table->json('target_external_user_ids')->nullable()->after('target_user_ids');
            $table->string('recurrence', 20)->nullable()->after('scheduled_at'); // daily
            $table->json('schedule_times')->nullable()->after('recurrence'); // ["08:00","12:00"]
            $table->json('recurrence_meta')->nullable()->after('schedule_times'); // fired slots tracker
            $table->boolean('is_recurring')->default(false)->after('recurrence_meta');
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table): void {
            $table->dropColumn([
                'target_external_user_ids',
                'recurrence',
                'schedule_times',
                'recurrence_meta',
                'is_recurring',
            ]);
        });
    }
};
