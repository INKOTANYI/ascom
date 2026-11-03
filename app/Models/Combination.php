<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An A-level combination (e.g. MPC) or a TVET trade (e.g. SOD). */
class Combination extends Model
{
    protected $fillable = ['code', 'name', 'type', 'is_offered'];

    protected $casts = ['is_offered' => 'boolean'];

    public const TYPES = ['a_level' => 'A-level combination', 'tvet_trade' => 'TVET trade'];
}
