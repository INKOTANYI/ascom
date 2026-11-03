<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Group 1 of the design: school identity, settings and the academic calendar.
 * Everything that names or brands the school lives here, so one codebase serves any school.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_profile', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('short_name', 40)->nullable();
            $table->string('mineduc_code', 20)->nullable()->unique();
            $table->string('school_type', 30);          // nursery, primary, secondary, tvet, mixed
            $table->string('ownership', 30)->nullable(); // public, government_aided, private
            $table->string('province', 50)->nullable();
            $table->string('district', 50);
            $table->string('sector', 50);
            $table->string('phone', 20)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('motto', 200)->nullable();
            $table->string('domain', 120)->nullable();
            $table->boolean('is_boarding')->default(false);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->jsonb('value');
            $table->timestamps();
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->smallInteger('term_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['academic_year_id', 'term_number']);
        });

        // BR-19: exactly three terms per year. BR-02: only one current year and one current term.
        DB::statement('ALTER TABLE terms ADD CONSTRAINT terms_term_number_check CHECK (term_number BETWEEN 1 AND 3)');
        DB::statement('ALTER TABLE terms ADD CONSTRAINT terms_dates_check CHECK (end_date >= start_date)');
        DB::statement('ALTER TABLE academic_years ADD CONSTRAINT academic_years_dates_check CHECK (end_date >= start_date)');
        DB::statement('CREATE UNIQUE INDEX academic_years_one_current ON academic_years (is_current) WHERE is_current');
        DB::statement('CREATE UNIQUE INDEX terms_one_current ON terms (is_current) WHERE is_current');
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('school_profile');
    }
};
