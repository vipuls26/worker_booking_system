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
            $table->time('start_time')->nullable()->after('booking_time');
            $table->time('end_time')->nullable()->after('start_time');
            $table->text('issue_description')->nullable()->after('notes');
            $table->text('rejection_reason')->nullable()->after('cancelled_reason');
            $table->index(['worker_id', 'booking_date', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['worker_id', 'booking_date', 'start_time']);
            $table->dropColumn(['start_time', 'end_time', 'issue_description', 'rejection_reason']);
        });
    }
};
