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
        Schema::table('worker_verifications', function (Blueprint $table) {
            $table->json('certificates')->nullable()->after('id_proof');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('worker_verifications', function (Blueprint $table) {
            $table->dropColumn('certificates');
        });
    }
};
