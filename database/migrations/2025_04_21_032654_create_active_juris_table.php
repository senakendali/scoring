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
        Schema::create('active_juris', function (Blueprint $table) {
            $table->id();
            $table->string('arena_name');
            $table->string('tournament_name');
            $table->enum('tipe_pertandingan', ['tanding', 'seni']);
            $table->unsignedTinyInteger('juri_number'); // 1 sampai 10
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_juris');
    }
};
