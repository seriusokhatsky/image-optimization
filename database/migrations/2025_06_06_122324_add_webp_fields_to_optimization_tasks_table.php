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
            $table->string('webp_path')->nullable()->after('optimized_path');
            $table->integer('webp_size')->nullable()->after('optimized_size');
            $table->decimal('webp_compression_ratio', 5, 2)->nullable()->after('compression_ratio');
            $table->integer('webp_size_reduction')->nullable()->after('size_reduction');
            $table->string('webp_processing_time')->nullable()->after('processing_time');
            $table->boolean('webp_generated')->default(false)->after('webp_processing_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('optimization_tasks', function (Blueprint $table) {
            $table->dropColumn([
                'webp_path',
                'webp_size',
                'webp_compression_ratio',
                'webp_size_reduction',
                'webp_processing_time',
                'webp_generated'
            ]);
        });
    }
};
