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
        Schema::create('worker_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('worker_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week')->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_off_day')->default(false)->index();
            $table->timestamps();

            $table->index(['worker_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worker_schedules');
    }
};
