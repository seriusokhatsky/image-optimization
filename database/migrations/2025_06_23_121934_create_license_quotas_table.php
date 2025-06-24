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
        Schema::create('license_quotas', function (Blueprint $table) {
            $table->id();
            $table->string('license_key')->index();
            $table->bigInteger('used_mb')->default(0);
            $table->bigInteger('current_quota_mb')->default(0); // Cached quota from API
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_quota_check')->nullable(); // When quota was last fetched
            $table->timestamps();
            
            $table->unique('license_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_quotas');
    }
};
