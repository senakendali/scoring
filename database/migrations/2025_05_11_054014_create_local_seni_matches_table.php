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
        Schema::create('local_seni_matches', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('remote_match_id')->nullable(); // 🆕 ID asli dari tabel seni_matches

            // 🆕 ID remote, untuk push kembali ke live
            $table->unsignedBigInteger('remote_contingent_id')->nullable();
            $table->unsignedBigInteger('remote_team_member_1')->nullable();
            $table->unsignedBigInteger('remote_team_member_2')->nullable();
            $table->unsignedBigInteger('remote_team_member_3')->nullable();

            $table->string('tournament_name');
            $table->string('arena_name');
            $table->date('match_date')->nullable();
            $table->time('match_time')->nullable();

            $table->string('pool_name'); // 🆕

            $table->string('category'); // Tunggal / Ganda / Regu
            $table->enum('match_type', ['seni_tunggal', 'seni_ganda', 'seni_regu']);
            $table->enum('gender', ['male', 'female']);

            $table->string('contingent_name');

            $table->string('participant_1')->nullable();
            $table->string('participant_2')->nullable();
            $table->string('participant_3')->nullable();

            $table->decimal('final_score', 5, 2)->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_seni_matches');
    }
};
