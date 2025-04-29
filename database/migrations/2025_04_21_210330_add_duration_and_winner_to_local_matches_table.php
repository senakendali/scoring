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
            $table->unsignedInteger('round_duration')->default(180)->after('total_rounds');
            $table->enum('winner_corner', ['red', 'blue'])->nullable()->after('status');
            $table->unsignedBigInteger('winner_id')->nullable();
            $table->string('winner_name')->nullable();
            $table->string('winner_contingent')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_matches', function (Blueprint $table) {
            $table->dropColumn(['round_duration', 'winner_corner', 'winner_id', 'winner_name', 'winner_contingent']);
        });
    }
};
