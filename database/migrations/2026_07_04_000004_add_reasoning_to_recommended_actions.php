<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommended_actions', function (Blueprint $table) {
            $table->text('reasoning')->nullable()->after('narrative');
        });
    }

    public function down(): void
    {
        Schema::table('recommended_actions', function (Blueprint $table) {
            $table->dropColumn('reasoning');
        });
    }
};
