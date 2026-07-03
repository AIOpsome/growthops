<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recommended_action_id')->constrained()->cascadeOnDelete();
            $table->string('actor');
            $table->string('from_status');
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->json('edited_value')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_audits');
    }
};
