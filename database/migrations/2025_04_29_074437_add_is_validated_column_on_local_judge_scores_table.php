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
        Schema::table('local_judge_scores', function (Blueprint $table) {
            $table->boolean('is_validated')->default(false)->after('scored_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_judge_scores', function (Blueprint $table) {
            $table->dropColumn('is_validated');
        });
    }
};
