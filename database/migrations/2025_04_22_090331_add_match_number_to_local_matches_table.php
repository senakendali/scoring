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
        Schema::table('local_matches', function (Blueprint $table) {
            $table->unsignedInteger('match_number')->nullable()->after('match_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_matches', function (Blueprint $table) {
            $table->dropColumn('match_number');
        });
    }
};
