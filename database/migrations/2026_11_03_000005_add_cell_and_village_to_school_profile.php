<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Rwandan addresses go down to cell (akagari) and village (umudugudu). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_profile', function (Blueprint $table) {
            $table->string('cell', 50)->nullable()->after('sector');
            $table->string('village', 50)->nullable()->after('cell');
        });
    }

    public function down(): void
    {
        Schema::table('school_profile', fn (Blueprint $table) => $table->dropColumn(['cell', 'village']));
    }
};
