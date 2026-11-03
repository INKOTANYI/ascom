<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Key/value school settings: colours, SMS sender, default language; later grading and weights. */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'array'];

    public const DEFAULTS = [
        'brand_color' => '#0E7C4A',
        'accent_color' => '#E3A008',
        'default_language' => 'en',
        'sms_sender' => '',
        'example_data' => false,
    ];

    /** Values are stored wrapped as {"v": ...} so any type fits in the JSONB column. */
    public static function values(): array
    {
        return Cache::rememberForever('settings', function () {
            $stored = static::query()->get()->mapWithKeys(fn ($row) => [$row->key => $row->value['v'] ?? null])->all();

            return array_merge(self::DEFAULTS, $stored);
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::values()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => ['v' => $value]]);
        Cache::forget('settings');
    }
}
