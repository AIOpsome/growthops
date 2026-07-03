<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('platform');
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->string('status')->default('active');
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('target_cpa', 10, 2)->nullable();
            $table->decimal('target_roas', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['platform', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
