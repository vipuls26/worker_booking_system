<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'account_status')) {
            return;
        }

        DB::table('users')
            ->where('is_blocked', true)
            ->update(['account_status' => 'fully_blocked']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'account_status')) {
            return;
        }

        DB::table('users')
            ->where('account_status', 'fully_blocked')
            ->update(['account_status' => 'active']);
    }
};
