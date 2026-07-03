<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommended_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('run_date');
            $table->string('type');
            $table->json('evidence');
            $table->decimal('confidence', 3, 2);
            $table->string('risk');
            $table->decimal('expected_upside', 12, 2);
            $table->string('status')->default('pending');
            $table->text('narrative')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'run_date', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommended_actions');
    }
};
