<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Ganti enum jadi include 'paused'
        DB::statement("ALTER TABLE local_seni_matches MODIFY status ENUM('not_started', 'ongoing', 'paused', 'finished') DEFAULT 'not_started'");
    }

    public function down()
    {
        // Balikin ke semula tanpa 'paused'
        DB::statement("ALTER TABLE local_seni_matches MODIFY status ENUM('not_started', 'ongoing', 'finished') DEFAULT 'not_started'");
    }
};
