<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('type', 20)->default('pwa'); // pwa | android | both
            $table->string('api_key', 80)->unique();
            $table->text('firebase_credentials')->nullable(); // encrypted service-account JSON
            $table->json('firebase_web_config')->nullable();
            $table->text('vapid_key')->nullable(); // encrypted
            $table->string('fcm_web_icon')->default('/icons/icon-192x192.png');
            $table->string('fcm_default_link')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
