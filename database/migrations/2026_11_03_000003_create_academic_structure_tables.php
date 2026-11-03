<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Group 2 of the design: levels, A-level combinations and TVET trades, classes, subjects,
 * the subjects each class takes (CAMIS "class group" + course), and topics.
 * Staff is created here too because classes and class subjects point to their teachers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();   // N1-N3, P1-P6, S1-S6, L3-L5
            $table->string('name', 50);
            $table->string('cycle', 30);            // nursery, primary, o_level, a_level, tvet
            $table->smallInteger('order_no');
            $table->boolean('is_offered')->default(true); // whether this school teaches the level
            $table->timestamps();
        });

        Schema::create('combinations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('type', 20);             // a_level or tvet_trade
            $table->boolean('is_offered')->default(true);
            $table->timestamps();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('staff_number', 30)->nullable()->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->char('gender', 1)->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('position', 60);         // head_teacher, deputy_studies, deputy_discipline, teacher, bursar, other
            $table->string('qualification', 120)->nullable();
            $table->string('employment_type', 20)->nullable();
            $table->date('hire_date')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->foreignId('combination_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('stream', 5)->nullable();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_teacher_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('name', 50);
            $table->smallInteger('capacity')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'name']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 120);
            $table->string('course_type', 30)->nullable();  // core, elective, specific, general, complementary
            $table->string('camis_course_code', 30)->nullable();
            $table->string('curriculum_ref', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->smallInteger('weekly_periods')->nullable();
            $table->decimal('max_marks', 5, 2)->nullable();
            $table->boolean('sdms_synced')->default(false);
            $table->timestamps();
            $table->unique(['class_id', 'subject_id']);
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained()->restrictOnDelete();
            $table->smallInteger('unit_no')->nullable();
            $table->string('title', 200);
            $table->smallInteger('term_number')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE staff ADD CONSTRAINT staff_gender_check CHECK (gender IN ('M','F'))");
        DB::statement('ALTER TABLE topics ADD CONSTRAINT topics_term_check CHECK (term_number BETWEEN 1 AND 3)');
        DB::statement('ALTER TABLE class_subjects ADD CONSTRAINT class_subjects_max_check CHECK (max_marks > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('combinations');
        Schema::dropIfExists('levels');
    }
};
