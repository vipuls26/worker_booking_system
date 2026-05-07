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
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['worker_id']);
            $table->foreignId('worker_id')->nullable()->change();
            $table->foreign('worker_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['worker_id']);
            $table->foreignId('worker_id')->nullable(false)->change();
            $table->foreign('worker_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
