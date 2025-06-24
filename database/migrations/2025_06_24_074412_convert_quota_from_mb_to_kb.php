<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('license_quotas', function (Blueprint $table) {
            // Rename columns to use KB instead of MB
            $table->renameColumn('used_mb', 'used_kb');
            $table->renameColumn('current_quota_mb', 'current_quota_kb');
        });

        // Convert existing MB values to KB (multiply by 1024)
        DB::table('license_quotas')->update([
            'used_kb' => DB::raw('used_kb * 1024'),
            'current_quota_kb' => DB::raw('current_quota_kb * 1024'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert KB values back to MB (divide by 1024)
        DB::table('license_quotas')->update([
            'used_kb' => DB::raw('FLOOR(used_kb / 1024)'),
            'current_quota_kb' => DB::raw('FLOOR(current_quota_kb / 1024)'),
        ]);

        Schema::table('license_quotas', function (Blueprint $table) {
            // Rename columns back to MB
            $table->renameColumn('used_kb', 'used_mb');
            $table->renameColumn('current_quota_kb', 'current_quota_mb');
        });
    }
};
