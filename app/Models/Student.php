<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use Audited;

    protected $fillable = [
        'sdms_id', 'reg_number', 'first_name', 'last_name', 'gender', 'date_of_birth', 'national_id', 'photo_path',
        'district', 'sector', 'special_needs', 'status', 'user_id', 'admission_date',
    ];

    protected $casts = ['date_of_birth' => 'date:Y-m-d', 'admission_date' => 'date:Y-m-d'];

    protected $appends = ['full_name'];

    protected $hidden = ['photo_path'];

    public const STATUSES = [
        'active' => 'Active',
        'suspended' => 'Suspended',
        'transferred' => 'Transferred',
        'dropped_out' => 'Dropped out',
        'graduated' => 'Graduated',
    ];

    /** BR-11: these students get no new marks or invoices. */
    public const CLOSED_STATUSES = ['transferred', 'dropped_out', 'graduated'];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function guardians()
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')->withPivot(['relationship', 'is_primary'])->withTimestamps();
    }

    public function primaryGuardian()
    {
        return $this->guardians()->wherePivot('is_primary', true);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }

    /** The enrolment of the current academic year, if any. */
    public function currentEnrollment()
    {
        return $this->hasOne(Enrollment::class)->whereHas('academicYear', fn ($q) => $q->where('is_current', true));
    }

    public function transfers()
    {
        return $this->hasMany(Transfer::class)->latest('transfer_date');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /** Search by name (either order), SDMS ID, registration number or a guardian's phone. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function ($q) use ($like, $term) {
            $q->whereRaw("lower(first_name || ' ' || last_name) like ?", [$like])
                ->orWhereRaw("lower(last_name || ' ' || first_name) like ?", [$like])
                ->orWhereRaw('lower(sdms_id) like ?', [$like])
                ->orWhereRaw('lower(reg_number) like ?', [$like]);
            $digits = preg_replace('/\D/', '', $term);
            if (strlen($digits) >= 6) {
                $q->orWhereHas('guardians', fn ($g) => $g->where('phone', 'like', '%'.substr($digits, -9).'%'));
            }
        });
    }
}
