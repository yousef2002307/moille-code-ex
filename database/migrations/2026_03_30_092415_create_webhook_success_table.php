<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_success', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id')->unique(); // Mollie payment ID
            $table->string('status'); // Payment status (paid, pending, failed, etc.)
            $table->json('webhook_data')->nullable(); // Raw webhook data
            $table->string('ip_address')->nullable(); // Request IP
            $table->timestamps();
            
            // Index for faster queries
            $table->index('payment_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_success');
    }
};
