<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table): void {
            $table->json('schedule_config')->nullable()->after('schedule_times');
            $table->string('image_url', 500)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['schedule_config', 'image_url']);
        });
    }
};
