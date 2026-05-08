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
        Schema::create('service_request_workers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('worker_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pricing_type')->nullable();
            $table->decimal('quoted_price', 10, 2)->nullable();
            $table->unsignedInteger('minimum_hours')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['service_request_id', 'worker_id']);
            $table->index(['worker_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_request_workers');
    }
};
