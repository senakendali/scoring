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
            $table->string('round_label')->nullable()->after('round_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_matches', function (Blueprint $table) {
            $table->dropColumn('round_label');
        });
    }
};
