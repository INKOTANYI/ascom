<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A subject or TVET module (CAMIS "course"). */
class Subject extends Model
{
    protected $fillable = ['code', 'name', 'course_type', 'camis_course_code', 'curriculum_ref', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public const COURSE_TYPES = [
        'core' => 'Core', 'elective' => 'Elective',
        'specific' => 'Specific module (TVET)', 'general' => 'General module (TVET)', 'complementary' => 'Complementary module (TVET)',
    ];

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }
}
