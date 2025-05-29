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
        Schema::create('optimization_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->unique(); // UUID for external reference
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('original_filename');
            $table->string('original_path');
            $table->string('optimized_path')->nullable();
            $table->integer('original_size');
            $table->integer('optimized_size')->nullable();
            $table->integer('quality')->default(80);
            $table->decimal('compression_ratio', 5, 2)->nullable();
            $table->integer('size_reduction')->nullable();
            $table->string('algorithm')->nullable();
            $table->string('processing_time')->nullable(); // in milliseconds
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Auto-delete after this time
            $table->timestamps();
            
            $table->index(['task_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optimization_tasks');
    }
}; 