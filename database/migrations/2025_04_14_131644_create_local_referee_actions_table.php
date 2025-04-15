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
        Schema::create('local_referee_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('local_match_id')->constrained()->onDelete('cascade');
            $table->foreignId('round_id')->constrained('local_match_rounds')->onDelete('cascade');
            $table->enum('corner', ['red', 'blue']);
            $table->enum('action', [
                'jatuhan',
                'binaan_1',
                'binaan_2',
                'teguran_1',
                'teguran_2',
                'peringatan_1',
                'peringatan_2',
            ]);
            $table->integer('point_change');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_referee_actions');
    }
};
