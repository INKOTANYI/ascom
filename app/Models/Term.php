<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Term extends Model
{
    protected $fillable = ['academic_year_id', 'term_number', 'start_date', 'end_date', 'is_current'];

    protected $casts = ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'is_current' => 'boolean'];

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public static function current(): ?self
    {
        return static::query()->where('is_current', true)->first();
    }

    /** BR-02: only one current term; its year becomes the current year. */
    public function makeCurrent(): void
    {
        DB::transaction(function () {
            static::query()->where('id', '!=', $this->id)->where('is_current', true)->update(['is_current' => false]);
            $this->update(['is_current' => true]);
            $this->academicYear->makeCurrent();
        });
    }
}
