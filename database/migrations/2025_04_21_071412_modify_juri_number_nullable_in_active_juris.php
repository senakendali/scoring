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
        Schema::table('active_juris', function (Blueprint $table) {
            $table->integer('juri_number')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('active_juris', function (Blueprint $table) {
            $table->integer('juri_number')->nullable(false)->change(); // revert ke NOT NULL
        });
    }
};
