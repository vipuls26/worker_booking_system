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
        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('total_amount', 'quoted_amount');
            $table->renameColumn('commission_rate', 'quoted_commission_rate');
            $table->renameColumn('platform_commission', 'quoted_platform_commission');
            $table->renameColumn('worker_earning', 'quoted_worker_earning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('quoted_amount', 'total_amount');
            $table->renameColumn('quoted_commission_rate', 'commission_rate');
            $table->renameColumn('quoted_platform_commission', 'platform_commission');
            $table->renameColumn('quoted_worker_earning', 'worker_earning');
        });
    }
};
