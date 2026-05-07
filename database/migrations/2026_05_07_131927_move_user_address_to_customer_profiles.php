<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('customer_profiles')->insertUsing(
            ['user_id', 'address', 'created_at', 'updated_at'],
            DB::table('users')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->selectRaw('users.id, users.address, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP')
                ->where('roles.slug', 'customer')
                ->whereNotNull('address')
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('address')->nullable()->after('phone');
        });

        DB::table('users')
            ->join('customer_profiles', 'customer_profiles.user_id', '=', 'users.id')
            ->update(['users.address' => DB::raw('customer_profiles.address')]);
    }
};
