<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

/** A student in a class for one academic year. One per student per year (BR-01). */
class Enrollment extends Model
{
    use Audited;

    protected $fillable = ['student_id', 'class_id', 'academic_year_id', 'enrollment_type', 'status', 'date_joined', 'date_left'];

    protected $casts = ['date_joined' => 'date:Y-m-d', 'date_left' => 'date:Y-m-d'];

    public const TYPES = [
        'new' => 'New student',
        'promoted' => 'Promoted',
        'repeating' => 'Repeating',
        'transfer_in' => 'Transferred in',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
