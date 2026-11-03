<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    protected $fillable = ['subject_id', 'level_id', 'unit_no', 'title', 'term_number'];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function level()
    {
        return $this->belongsTo(Level::class);
    }
}
