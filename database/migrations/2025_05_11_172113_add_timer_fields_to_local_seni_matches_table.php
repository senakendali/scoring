<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::table('local_seni_matches', function (Blueprint $table) {
            $table->timestamp('start_time')->nullable()->after('final_score');
            $table->timestamp('pause_time')->nullable()->after('start_time');
            $table->integer('duration')->nullable()->default(180)->after('pause_time');
            $table->timestamp('end_time')->nullable()->after('duration');
        });
    }

    public function down(): void
    {
        Schema::table('local_seni_matches', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'pause_time', 'duration', 'end_time']);
        });
    }
};
