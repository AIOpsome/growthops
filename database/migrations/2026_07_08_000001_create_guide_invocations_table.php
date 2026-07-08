<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_invocations', function (Blueprint $table) {
            $table->id();
            $table->string('actor');
            $table->string('workflow');
            $table->string('intent')->nullable();
            $table->boolean('confirmed')->default(false);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['workflow', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_invocations');
    }
};
