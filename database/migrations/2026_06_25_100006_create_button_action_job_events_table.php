<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('button_action_job_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('button_action_job_id')->constrained('button_action_jobs')->cascadeOnDelete();
            $table->string('type');
            $table->string('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['button_action_job_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('button_action_job_events');
    }
};
