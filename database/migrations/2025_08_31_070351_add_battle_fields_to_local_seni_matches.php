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
        Schema::table('local_seni_matches', function (Blueprint $table) {
            // === Fields untuk sinkron dari API (battle-ready) ===
            // mode: default/battle
            if (!Schema::hasColumn('local_seni_matches', 'mode')) {
                $table->enum('mode', ['default', 'battle'])
                      ->default('default')
                      ->after('remote_team_member_3');
                $table->index('mode', 'local_seni_matches_mode_index');
            }

            // group pasangan per round (untuk sepasang red/blue)
            if (!Schema::hasColumn('local_seni_matches', 'battle_group')) {
                $table->unsignedInteger('battle_group')->nullable()->after('mode');
            }

            // nomor babak (1..N), label babak, dan prioritas untuk sorting
            if (!Schema::hasColumn('local_seni_matches', 'round')) {
                $table->unsignedSmallInteger('round')->nullable()->after('battle_group');
            }
            if (!Schema::hasColumn('local_seni_matches', 'round_label')) {
                $table->string('round_label', 50)->nullable()->after('round');
            }
            if (!Schema::hasColumn('local_seni_matches', 'round_priority')) {
                $table->unsignedSmallInteger('round_priority')->nullable()->after('round_label');
            }

            // corner & winner_corner (khusus battle)
            if (!Schema::hasColumn('local_seni_matches', 'corner')) {
                $table->enum('corner', ['red', 'blue'])->nullable()->after('round_priority');
            }
            if (!Schema::hasColumn('local_seni_matches', 'winner_corner')) {
                $table->enum('winner_corner', ['red', 'blue'])->nullable()->after('corner');
            }

            // parent pointers (relasi ke match lokal yang di atasnya)
            if (!Schema::hasColumn('local_seni_matches', 'parent_match_red_id')) {
                $table->unsignedBigInteger('parent_match_red_id')->nullable()->after('winner_corner');
                $table->foreign('parent_match_red_id', 'local_seni_matches_parent_red_fk')
                      ->references('id')->on('local_seni_matches')->nullOnDelete();
            }
            if (!Schema::hasColumn('local_seni_matches', 'parent_match_blue_id')) {
                $table->unsignedBigInteger('parent_match_blue_id')->nullable()->after('parent_match_red_id');
                $table->foreign('parent_match_blue_id', 'local_seni_matches_parent_blue_fk')
                      ->references('id')->on('local_seni_matches')->nullOnDelete();
            }

            // indeks komposit buat query battle per pool/round/group
            if (!Schema::hasColumn('local_seni_matches', 'battle_group')) {
                // no-op: sudah ditambahkan di atas
            }
            // Tambah index komposit jika belum ada (nama mengikuti konvensi)
            //$table->index(['pool_id', 'round', 'battle_group'], 'local_seni_matches_pool_round_group_index');

            // (Opsional) cegah duplikasi slot: satu (pool, round, group, corner) hanya sekali
            // $table->unique(['pool_id','round','battle_group','corner'], 'local_seni_matches_battle_slot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_seni_matches', function (Blueprint $table) {
             // drop indexes (gunakan nama yang kita set)
            if (Schema::hasColumn('local_seni_matches', 'mode')) {
                $table->dropIndex('local_seni_matches_mode_index');
            }
            $table->dropIndex('local_seni_matches_pool_round_group_index');

            // drop foreign keys lebih dulu
            if (Schema::hasColumn('local_seni_matches', 'parent_match_red_id')) {
                $table->dropForeign('local_seni_matches_parent_red_fk');
            }
            if (Schema::hasColumn('local_seni_matches', 'parent_match_blue_id')) {
                $table->dropForeign('local_seni_matches_parent_blue_fk');
            }

            // (Opsional) kalau tadi aktifkan unique, hapus juga:
            // $table->dropUnique('local_seni_matches_battle_slot_unique');

            // drop columns (aman kalau ada sebagian yg belum dibuat)
            foreach ([
                'parent_match_blue_id',
                'parent_match_red_id',
                'winner_corner',
                'corner',
                'round_priority',
                'round_label',
                'round',
                'battle_group',
                'mode',
            ] as $col) {
                if (Schema::hasColumn('local_seni_matches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
