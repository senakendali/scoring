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
        Schema::table('match_personnel_assignments', function (Blueprint $table) {
            Schema::rename('active_juris', 'match_personnel_assignments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_personnel_assignments', function (Blueprint $table) {
            Schema::rename('match_personnel_assignments', 'active_juris');
        });
    }
};
