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
        Schema::create('local_seni_penalties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('local_match_id');
            $table->string('reason');            // Misal: "Terlambat", "Atribut tidak lengkap"
            $table->decimal('penalty_value', 4, 2); // Misal: 0.25
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_seni_penalties');
    }
};
