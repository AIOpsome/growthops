<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recommended_actions', function (Blueprint $table) {
            $table->decimal('applied_parameter', 12, 2)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('recommended_actions', function (Blueprint $table) {
            $table->dropColumn('applied_parameter');
        });
    }
};
