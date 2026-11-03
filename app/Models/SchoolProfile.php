<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** The one record that names the school. Everything on screen reads it; nothing is hard-coded. */
class SchoolProfile extends Model
{
    protected $table = 'school_profile';

    protected $fillable = [
        'name', 'short_name', 'mineduc_code', 'school_type', 'ownership', 'province', 'district', 'sector',
        'phone', 'email', 'logo_path', 'motto', 'domain', 'is_boarding',
    ];

    protected $casts = ['is_boarding' => 'boolean'];

    public const TYPES = ['nursery' => 'Nursery', 'primary' => 'Primary', 'secondary' => 'Secondary', 'tvet' => 'TVET', 'mixed' => 'Mixed (several levels)'];

    public const OWNERSHIP = ['public' => 'Public', 'government_aided' => 'Government-aided', 'private' => 'Private'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('school_profile'));
    }

    public static function current(): ?self
    {
        return Cache::rememberForever('school_profile', fn () => static::query()->orderBy('id')->first());
    }
}
