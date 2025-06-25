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
        Schema::table('local_seni_matches', function (Blueprint $table) {
            $table->enum('medal', ['emas', 'perak', 'perunggu'])->nullable()->after('final_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_seni_matches', function (Blueprint $table) {
            $table->dropColumn('medal');
        });
    }
};
