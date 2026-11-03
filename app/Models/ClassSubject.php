<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A subject taught to one class, with its teacher (must match SDMS before marks entry). */
class ClassSubject extends Model
{
    protected $fillable = ['class_id', 'subject_id', 'teacher_id', 'weekly_periods', 'max_marks', 'sdms_synced'];

    protected $casts = ['sdms_synced' => 'boolean'];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Staff::class, 'teacher_id');
    }
}
