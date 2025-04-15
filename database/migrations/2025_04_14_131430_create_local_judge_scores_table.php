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
        Schema::create('local_judge_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_match_id')->constrained()->onDelete('cascade');
            $table->foreignId('round_id')->constrained('local_match_rounds')->onDelete('cascade');
            $table->unsignedTinyInteger('judge_number'); // 1, 2, 3
            $table->enum('corner', ['red', 'blue']);
            $table->enum('type', ['punch', 'kick']);
            $table->unsignedTinyInteger('point'); // 1 or 2
            $table->timestamp('scored_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_judge_scores');
    }
};
