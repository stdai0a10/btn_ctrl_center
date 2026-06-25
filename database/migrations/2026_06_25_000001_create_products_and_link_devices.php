<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 20)->unique();
            $table->string('model_number')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('product_functions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('description');
            $table->timestamps();

            $table->index('product_id');
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->foreignId('product_id')
                ->nullable()
                ->after('id')
                ->constrained('products')
                ->restrictOnDelete();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::dropIfExists('product_functions');
        Schema::dropIfExists('products');
    }
};
