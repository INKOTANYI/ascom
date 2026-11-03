<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    protected $fillable = ['code', 'name', 'cycle', 'order_no', 'is_offered'];

    protected $casts = ['is_offered' => 'boolean'];

    public const CYCLES = ['nursery' => 'Nursery', 'primary' => 'Primary', 'o_level' => 'O-level', 'a_level' => 'A-level', 'tvet' => 'TVET'];

    public function classes()
    {
        return $this->hasMany(SchoolClass::class);
    }
}
