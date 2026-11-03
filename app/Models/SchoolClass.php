<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A class (CAMIS "class group"), e.g. S4 SOD A, in one academic year. */
class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $fillable = ['level_id', 'combination_id', 'stream', 'academic_year_id', 'class_teacher_id', 'name', 'capacity'];

    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    public function combination()
    {
        return $this->belongsTo(Combination::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classTeacher()
    {
        return $this->belongsTo(Staff::class, 'class_teacher_id');
    }

    public function classSubjects()
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    /** "S4" + "SOD" + "A" gives "S4 SOD A". */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    /** Students currently in the class. */
    public function activeEnrollments()
    {
        return $this->enrollments()->where('status', 'active');
    }

    public static function makeName(Level $level, ?Combination $combination, ?string $stream): string
    {
        return trim(implode(' ', array_filter([$level->code, $combination?->code, $stream ? strtoupper($stream) : null])));
    }
}
