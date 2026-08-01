<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('button_page_items', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 20)->unique();
            $table->foreignId('button_page_id')->constrained('button_pages')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->foreignId('product_function_id')->constrained('product_functions')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('shape')->default('rounded_square');
            $table->string('background_color', 7)->default('#2563EB');
            $table->string('content_type')->default('icon');
            $table->string('icon_key', 50)->nullable();
            $table->string('label', 100)->nullable();
            $table->string('foreground_color', 7)->default('#FFFFFF');
            $table->timestamps();

            $table->unique(['button_page_id', 'position']);
            $table->index('device_id');
            $table->index('product_function_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('button_page_items');
    }
};
