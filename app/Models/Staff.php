<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'staff_number', 'first_name', 'last_name', 'gender', 'phone', 'national_id', 'position',
        'qualification', 'employment_type', 'hire_date', 'user_id', 'is_active',
    ];

    protected $casts = ['hire_date' => 'date:Y-m-d', 'is_active' => 'boolean'];

    protected $appends = ['full_name'];

    public const POSITIONS = [
        'head_teacher' => 'Head teacher',
        'deputy_studies' => 'Deputy head for studies (DOS)',
        'deputy_discipline' => 'Deputy head for discipline',
        'teacher' => 'Teacher',
        'bursar' => 'Bursar / accountant',
        'other' => 'Other staff',
    ];

    public const EMPLOYMENT = ['permanent' => 'Permanent', 'contract' => 'Contract', 'part_time' => 'Part-time'];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
