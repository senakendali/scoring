<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::statement("
            ALTER TABLE local_seni_matches 
            MODIFY COLUMN match_type ENUM(
                'seni_tunggal',
                'seni_ganda',
                'seni_regu',
                'solo_kreatif'
            ) NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Optional: balik ke enum awal
        DB::statement("
            ALTER TABLE local_seni_matches 
            MODIFY COLUMN match_type ENUM(
                'seni_tunggal',
                'seni_ganda',
                'seni_regu'
            ) NOT NULL
        ");
    }
};
