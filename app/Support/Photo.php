<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Passport photos: shrunk to at most 600 px and saved as JPEG on the private disk. */
class Photo
{
    public static function store(UploadedFile $file, string $folder, int|string $name): string
    {
        $path = "{$folder}/{$name}-".substr(md5((string) microtime(true)), 0, 6).'.jpg';
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $source) {
            // Not readable by GD: keep the original file as it is.
            return $file->storeAs($folder, "{$name}.".$file->extension(), 'local');
        }

        $source = self::orient($source, $file);
        [$w, $h] = [imagesx($source), imagesy($source)];
        $scale = min(1, 600 / max($w, $h));
        $image = imagecreatetruecolor((int) round($w * $scale), (int) round($h * $scale));
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), $w, $h);

        ob_start();
        imagejpeg($image, null, 82);
        Storage::disk('local')->put($path, (string) ob_get_clean());

        return $path;
    }

    /** Phone photos are often stored sideways with an EXIF rotation flag. */
    private static function orient(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || $file->getMimeType() !== 'image/jpeg') {
            return $image;
        }
        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
