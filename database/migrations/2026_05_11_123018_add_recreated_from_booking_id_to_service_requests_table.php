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
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->foreignId('recreated_from_booking_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('bookings')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recreated_from_booking_id');
        });
    }
};
