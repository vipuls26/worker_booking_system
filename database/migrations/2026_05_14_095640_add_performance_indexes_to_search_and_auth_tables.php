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
        Schema::table('users', function (Blueprint $table) {
            $table->index(
                ['role_id', 'account_status', 'is_verified', 'email_verified_at'],
                'users_role_status_verified_email_index'
            );
        });

        Schema::table('worker_profiles', function (Blueprint $table) {
            $table->index(
                ['is_verified', 'city', 'experience_years'],
                'worker_profiles_verified_city_experience_index'
            );
        });

        Schema::table('worker_services', function (Blueprint $table) {
            $table->index(
                ['service_id', 'approval_status', 'is_active', 'price'],
                'worker_services_service_approval_active_price_index'
            );
        });

        Schema::table('worker_schedules', function (Blueprint $table) {
            $table->index(
                ['day_of_week', 'is_off_day', 'start_time', 'end_time', 'worker_id'],
                'worker_schedules_search_window_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_status_verified_email_index');
        });

        Schema::table('worker_profiles', function (Blueprint $table) {
            $table->dropIndex('worker_profiles_verified_city_experience_index');
        });

        Schema::table('worker_services', function (Blueprint $table) {
            $table->dropIndex('worker_services_service_approval_active_price_index');
        });

        Schema::table('worker_schedules', function (Blueprint $table) {
            $table->dropIndex('worker_schedules_search_window_index');
        });
    }
};
