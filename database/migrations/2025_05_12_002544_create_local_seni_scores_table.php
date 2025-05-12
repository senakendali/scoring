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
        Schema::create('local_seni_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_match_id'); // pertandingan seni
            $table->unsignedBigInteger('judge_number'); // 1, 2, 3, 4, 5 (jumlah juri bervariasi)
            
            $table->decimal('deduction', 4, 2); // Potongan nilai, contoh: 0.05
            $table->timestamp('deducted_at');   // Waktu ketika juri menekan tombol
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_seni_scores');
    }
};
