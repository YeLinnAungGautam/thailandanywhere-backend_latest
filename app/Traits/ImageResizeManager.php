<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

trait ImageResizeManager
{
    protected ?ImageManager $imageManager = null;

    protected function imageManager(): ImageManager
    {
        // Swap Driver::class for \Intervention\Image\Drivers\Imagick\Driver if you have imagick installed
        return $this->imageManager ??= ImageManager::usingDriver(Driver::class);
    }

    /**
     * Resize + compress an uploaded image before storing it, so pages load fast.
     * Downsizes only (never upscales), auto-orients from EXIF, re-encodes at
     * the given quality. PNG/WebP keep their format (for transparency); anything
     * else is converted to JPG since it compresses better for photos.
     */
    public function uploadResizedImage(UploadedFile $file, string $path, int $maxWidth = 1600, int $quality = 75): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $outputExt = in_array($extension, ['png', 'webp']) ? $extension : 'jpg';

        $fileName = time() . '_' . rand(10000, 99999) . '_' . uniqid() . '.' . $outputExt;
        $filePath = rtrim($path, '/') . '/' . $fileName;

        // UploadedFile implements SplFileInfo, decode() accepts it directly
        $image = $this->imageManager()->decode($file);

        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $format = match ($outputExt) {
            'png'   => Format::PNG,
            'webp'  => Format::WEBP,
            default => Format::JPEG,
        };

        $encoded = $image->encodeUsingFormat($format, quality: $quality);

        Storage::put($filePath, (string) $encoded);

        return [
            'fileName' => $fileName,
            'fileType' => $outputExt,
            'filePath' => $filePath,
            'fileSize' => $this->formatBytes(strlen((string) $encoded)),
            'width'    => $image->width(),
            'height'   => $image->height(),
        ];
    }

    public function deleteImage(?string $path): void
    {
        if ($path && Storage::exists($path)) {
            Storage::delete($path);
        }
    }

    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 bytes';
        }

        $base = log($bytes) / log(1024);
        $suffixes = [' bytes', ' KB', ' MB', ' GB', ' TB'];

        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }
}
