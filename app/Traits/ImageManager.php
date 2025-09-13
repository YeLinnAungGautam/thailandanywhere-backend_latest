<?php

namespace App\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

trait ImageManager
{
    public function uploads($file, $path)
    {
        if ($file) {
            $file_type = $file->getClientOriginalExtension();
            $fileName = time() . '_' . rand(00000, 99999) . '_' . uniqid() . '.' . $file_type;

            Storage::put($path . $fileName, File::get($file));
            $filePath = $path . $fileName;

            return $file = [
                'fileName' => $fileName,
                'fileType' => $file_type,
                'filePath' => $filePath,
                'fileSize' => $this->fileSize($file)
            ];
        }
    }

    public function fileSize($file, $precision = 2)
    {
        $size = $file->getSize();

        if ($size > 0) {
            $size = (int) $size;
            $base = log($size) / log(1024);
            $suffixes = [' bytes', ' KB', ' MB', ' GB', ' TB'];

            return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
        }

        return $size;
    }
}
