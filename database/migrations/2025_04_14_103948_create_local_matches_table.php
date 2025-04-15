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
        Schema::create('local_matches', function (Blueprint $table) {
            $table->id();

            // Optional ID dari sistem utama
            $table->unsignedBigInteger('remote_match_id')->nullable();

            // Info arena & kategori
            $table->string('arena_name')->nullable();
            $table->string('class_name')->nullable();
            $table->string('match_code')->nullable();
            $table->unsignedTinyInteger('total_rounds')->default(3);

            // Status pertandingan
            $table->enum('status', ['not_started', 'in_progress', 'finished'])->default('not_started');

            // Peserta Merah
            $table->unsignedBigInteger('red_id')->nullable();
            $table->string('red_name');
            $table->string('red_contingent');

            // Peserta Biru
            $table->unsignedBigInteger('blue_id')->nullable();
            $table->string('blue_name');
            $table->string('blue_contingent');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_matches');
    }
};
