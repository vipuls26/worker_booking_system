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
        Schema::table('reviews', function (Blueprint $table): void {
            $table->index('booking_id', 'reviews_booking_id_foreign_index');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['booking_id']);
            $table->string('type')->default('customer_to_worker')->after('worker_id')->index();
            $table->unique(['booking_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['booking_id', 'type']);
            $table->dropColumn('type');
            $table->unique('booking_id');
            $table->dropIndex('reviews_booking_id_foreign_index');
        });
    }
};
