<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('button_action_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 20)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('request_id');
            $table->foreignId('button_page_item_id')->nullable()->constrained('button_page_items')->nullOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('product_function_id')->constrained('product_functions')->restrictOnDelete();
            $table->string('status');
            $table->string('source')->default('button');
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('progress_message')->nullable();
            $table->json('result')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('locked_by_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('front_end_timeout_at')->nullable();
            $table->timestamp('last_progress_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'status']);
            $table->index(['device_id', 'status', 'created_at']);
            $table->index('product_function_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('button_action_jobs');
    }
};
