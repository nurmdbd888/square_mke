<?php

namespace App\Support\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CatalogImageStore
{
    public function store(UploadedFile $upload, string $directory): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('The GD extension is required to process catalog images.');
        }

        $contents = file_get_contents($upload->getRealPath());
        if ($contents === false) {
            throw ValidationException::withMessages(['image' => __('The image could not be read.')]);
        }

        $dimensions = @getimagesizefromstring($contents);
        if ($dimensions === false || $dimensions[0] > 10000 || $dimensions[1] > 10000 || $dimensions[0] * $dimensions[1] > 40000000) {
            throw ValidationException::withMessages(['image' => __('Image dimensions are too large or the file is invalid.')]);
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw ValidationException::withMessages(['image' => __('Upload a valid JPEG, PNG, or WebP image.')]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $left = intdiv($width - $side, 2);
        $top = intdiv($height - $side, 2);
        $square = imagecreatetruecolor(800, 800);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        $transparent = imagecolorallocatealpha($square, 255, 255, 255, 127);
        imagefill($square, 0, 0, $transparent);
        imagecopyresampled($square, $source, 0, 0, $left, $top, 800, 800, $side, $side);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'catalog-image-');
        if ($temporaryPath === false) {
            imagedestroy($source);
            imagedestroy($square);
            throw new \RuntimeException('Unable to allocate a temporary image file.');
        }

        try {
            if (! imagepng($square, $temporaryPath, 7)) {
                throw new \RuntimeException('Unable to normalize catalog image.');
            }

            $path = trim($directory, '/').'/'.Str::uuid().'.png';
            $normalized = file_get_contents($temporaryPath);
            if ($normalized === false) {
                throw new \RuntimeException('Unable to read normalized catalog image.');
            }

            $stored = Storage::disk('public')->put($path, $normalized);
            if (! $stored) {
                throw new \RuntimeException('Unable to store catalog image.');
            }

            return $path;
        } finally {
            imagedestroy($source);
            imagedestroy($square);
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    public function delete(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'catalog/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
