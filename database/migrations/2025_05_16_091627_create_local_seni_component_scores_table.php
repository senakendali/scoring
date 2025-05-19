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
        Schema::create('local_seni_component_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_match_id');
            $table->unsignedTinyInteger('judge_number'); // 1 - 10
            $table->decimal('attack_defense_technique', 4, 2)->nullable(); // Misal: 9.45
            $table->decimal('firmness_harmony', 4, 2)->nullable();
            $table->decimal('soulfulness', 4, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['local_match_id', 'judge_number']); // 1 data per juri per match
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_seni_component_scores');
    }
};
