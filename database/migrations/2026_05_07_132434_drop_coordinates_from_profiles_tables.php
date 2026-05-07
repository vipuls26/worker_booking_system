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
        if (Schema::hasColumn('worker_profiles', 'latitude') || Schema::hasColumn('worker_profiles', 'longitude')) {
            Schema::table('worker_profiles', function (Blueprint $table): void {
                $table->dropColumn(['latitude', 'longitude']);
            });
        }

        if (Schema::hasColumn('customer_profiles', 'latitude') || Schema::hasColumn('customer_profiles', 'longitude')) {
            Schema::table('customer_profiles', function (Blueprint $table): void {
                $table->dropColumn(['latitude', 'longitude']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('worker_profiles', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }
};
