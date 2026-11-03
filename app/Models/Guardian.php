<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

/** A parent or guardian. Brothers and sisters share one guardian record. */
class Guardian extends Model
{
    use Audited;

    protected $fillable = ['first_name', 'last_name', 'phone', 'email', 'national_id', 'occupation', 'user_id'];

    protected $appends = ['full_name'];

    public const RELATIONSHIPS = ['father' => 'Father', 'mother' => 'Mother', 'guardian' => 'Guardian'];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_guardians')->withPivot(['relationship', 'is_primary'])->withTimestamps();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
