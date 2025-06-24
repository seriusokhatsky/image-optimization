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
        Schema::table('license_quotas', function (Blueprint $table) {
            $table->dropUnique(['license_key']);
            $table->renameColumn('license_key', 'token');
            $table->unique('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_quotas', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->renameColumn('token', 'license_key');
            $table->unique('license_key');
        });
    }
};
