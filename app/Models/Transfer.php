<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

/** A move to or from another school. */
class Transfer extends Model
{
    use Audited;

    protected $fillable = ['student_id', 'direction', 'other_school', 'other_school_code', 'transfer_date', 'reason', 'recorded_by'];

    protected $casts = ['transfer_date' => 'date:Y-m-d'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
