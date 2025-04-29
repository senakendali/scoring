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
        Schema::table('local_matches', function (Blueprint $table) {
            $table->string('pool_name')->nullable()->after('class_name');
            $table->unsignedTinyInteger('round_level')->default(1)->after('total_rounds');
            $table->unsignedBigInteger('parent_match_red_id')->nullable()->after('round_level');
            $table->unsignedBigInteger('parent_match_blue_id')->nullable()->after('parent_match_red_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('local_matches', function (Blueprint $table) {
            $table->dropColumn([
                'pool_name',
                'round_level',
                'parent_match_red_id',
                'parent_match_blue_id'
            ]);
        });
    }
};
