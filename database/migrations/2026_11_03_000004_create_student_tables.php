<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Students, guardians, enrolment and transfers (FR-04, FR-05), and the audit log (FR-29, BR-15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('sdms_id', 30)->nullable()->unique();     // SDMS unique learner ID (Ministerial Order 001/2021 Art. 3)
            $table->string('reg_number', 30)->nullable()->unique();  // school registration number
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->char('gender', 1);
            $table->date('date_of_birth')->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('district', 50)->nullable();
            $table->string('sector', 50)->nullable();
            $table->text('special_needs')->nullable();
            $table->string('status', 20)->default('active');         // active, transferred, dropped_out, graduated, suspended
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // student login (optional)
            $table->date('admission_date')->nullable();
            $table->timestamps();
            $table->index(['last_name', 'first_name']);
            $table->index('status');
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('phone', 15)->nullable();                  // +2507XXXXXXXX, used for SMS and USSD
            $table->string('email', 120)->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('occupation', 80)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // parent login
            $table->timestamps();
            $table->index('phone');
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->restrictOnDelete();
            $table->string('relationship', 30);                      // father, mother, guardian
            $table->boolean('is_primary')->default(false);           // receives SMS (BR-10)
            $table->timestamps();
            $table->primary(['student_id', 'guardian_id']);
        });

        // An enrolment must point at a class of the same academic year.
        Schema::table('classes', fn (Blueprint $table) => $table->unique(['id', 'academic_year_id']));

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id');
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('enrollment_type', 20)->default('new');   // new, promoted, repeating, transfer_in
            $table->string('status', 20)->default('active');         // active, left
            $table->date('date_joined')->nullable();
            $table->date('date_left')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'academic_year_id']);      // BR-01
            $table->foreign(['class_id', 'academic_year_id'])->references(['id', 'academic_year_id'])->on('classes')->restrictOnDelete();
            $table->index(['class_id', 'status']);
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 5);                          // in, out
            $table->string('other_school', 200)->nullable();
            $table->string('other_school_code', 20)->nullable();
            $table->date('transfer_date');
            $table->text('reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);                            // create, update, delete, login, export, import
            $table->string('table_name', 60);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['table_name', 'record_id']);
            $table->index('created_at');
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_gender_check CHECK (gender IN ('M','F'))");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_check CHECK (status IN ('active','transferred','dropped_out','graduated','suspended'))");
        DB::statement("ALTER TABLE student_guardians ADD CONSTRAINT student_guardians_relationship_check CHECK (relationship IN ('father','mother','guardian'))");
        DB::statement('CREATE UNIQUE INDEX student_guardians_one_primary ON student_guardians (student_id) WHERE is_primary');
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_type_check CHECK (enrollment_type IN ('new','promoted','repeating','transfer_in'))");
        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_status_check CHECK (status IN ('active','left'))");
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_dates_check CHECK (date_left IS NULL OR date_joined IS NULL OR date_left >= date_joined)');
        DB::statement("ALTER TABLE transfers ADD CONSTRAINT transfers_direction_check CHECK (direction IN ('in','out'))");
        DB::statement('CREATE INDEX students_search ON students (lower(first_name || \' \' || last_name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('enrollments');
        Schema::table('classes', fn (Blueprint $table) => $table->dropUnique(['id', 'academic_year_id']));
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};
