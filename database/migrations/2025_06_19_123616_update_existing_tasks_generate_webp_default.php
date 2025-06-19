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
        // Update existing records that were created with the old default (true) to the new default (false)
        // Only update records where generate_webp is currently true (old default)
        DB::table('optimization_tasks')->where('generate_webp', true)->update(['generate_webp' => false]);
        
        // Change the column default from true to false
        Schema::table('optimization_tasks', function (Blueprint $table) {
            $table->boolean('generate_webp')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the column default back to true
        Schema::table('optimization_tasks', function (Blueprint $table) {
            $table->boolean('generate_webp')->default(true)->change();
        });
        
        // Revert existing records back to true
        DB::table('optimization_tasks')->where('generate_webp', false)->update(['generate_webp' => true]);
    }
};
