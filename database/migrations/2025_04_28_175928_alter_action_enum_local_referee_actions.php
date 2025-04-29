<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterActionEnumLocalRefereeActions extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE local_referee_actions MODIFY COLUMN action ENUM(
            'jatuhan', 
            'binaan_1', 
            'binaan_2', 
            'teguran_1', 
            'teguran_2', 
            'peringatan_1', 
            'peringatan_2', 
            'verifikasi_jatuhan', 
            'verifikasi_hukuman'
        ) NOT NULL");
    }

    public function down()
    {
        DB::statement("ALTER TABLE local_referee_actions MODIFY COLUMN action ENUM(
            'jatuhan', 
            'binaan_1', 
            'binaan_2', 
            'teguran_1', 
            'teguran_2', 
            'peringatan_1', 
            'peringatan_2'
        ) NOT NULL");
    }
}
