<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

if (!function_exists('uploadFile')) {
    function uploadFile(
        UploadedFile $file,
        string $path,
        ?string $upload_file_name = null
    ): string {
        $file_name = $upload_file_name ?? uniqid() . '_' . date('Y-m-d-H-i-s') . '.' . $file->getClientOriginalExtension();

        Storage::disk('public')->put($path . $file_name, File::get($file));

        return $file_name;
    }
}
