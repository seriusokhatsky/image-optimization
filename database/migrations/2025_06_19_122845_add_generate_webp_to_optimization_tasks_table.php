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
        Schema::table('optimization_tasks', function (Blueprint $table) {
            $table->boolean('generate_webp')->default(false)->after('quality');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('optimization_tasks', function (Blueprint $table) {
            $table->dropColumn('generate_webp');
        });
    }
};
