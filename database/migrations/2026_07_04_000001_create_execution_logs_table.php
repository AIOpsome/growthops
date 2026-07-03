<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommended_action_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('simulated');
            $table->string('platform');
            $table->string('simulated_endpoint');
            $table->json('simulated_payload');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_logs');
    }
};
