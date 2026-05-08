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
            $table->decimal('commission_rate', 5, 2)->default(10)->after('total_amount');
            $table->decimal('platform_commission', 10, 2)->default(0)->after('commission_rate');
            $table->decimal('worker_earning', 10, 2)->default(0)->after('platform_commission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn(['commission_rate', 'platform_commission', 'worker_earning']);
        });
    }
};
