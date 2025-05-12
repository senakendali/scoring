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
            $table->enum('disqualified', ['yes', 'no'])->after('final_score')->default('no');
            $table->string('disqualification_reason')->after('disqualified')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_seni_matches', function (Blueprint $table) {
            $table->dropColumn('disqualified');
            $table->dropColumn('disqualification_reason');
        });
    }
};
