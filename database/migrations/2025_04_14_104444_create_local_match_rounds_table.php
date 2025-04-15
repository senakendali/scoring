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
        Schema::create('local_match_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_match_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('round_number');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->enum('status', ['not_started', 'in_progress', 'paused', 'finished'])->default('not_started');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_match_rounds');
    }
};
