<?php

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (!function_exists('uploadFile')) {
    function uploadFile(
        UploadedFile $file,
        string $path,
        ?string $upload_file_name = null
    ): string {
        $file_name = $upload_file_name ?? uniqid() . '_' . date('Y-m-d-H-i-s') . '.' . $file->getClientOriginalExtension();

        Storage::put($path . $file_name, File::get($file));

        return $file_name;
    }
}

if (!function_exists('get_file_link')) {
    function get_file_link($path, $file_name)
    {
        return Storage::url($path . '/' . $file_name);
    }
}

if (!function_exists('make_title')) {
    function make_title($string)
    {
        return Str::of($string)->snake()->replace('_', ' ')->title();
    }
}

if (!function_exists('upload_file')) {
    function upload_file($file, $path, ?string $upload_file_name = null)
    {
        if ($file) {
            // $fileName = time() . '_' . rand(00000, 99999) . '_' . uniqid();
            $fileName = $upload_file_name ?? uniqid().'_'.date('Y-m-d-H-i-s').'.'.$file->getClientOriginalExtension();

            Storage::put($path . $fileName, File::get($file));

            $file_type = $file->getClientOriginalExtension();
            $filePath = $path . $fileName;

            return $file = [
                'fileName' => $fileName,
                'fileType' => $file_type,
                'filePath' => $filePath,
                'fileSize' => get_file_size($file),
                'file' => $fileName,
            ];
        }
    }
}

if (!function_exists('get_file_size')) {
    function get_file_size($file, $precision = 2)
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

if (!function_exists('get_file')) {
    function get_file($file_name, $path)
    {
        if ($file_name) {
            return Storage::url($path . '/' . $file_name);
        }

        return null;
    }
}

if (!function_exists('delete_file')) {
    function delete_file($path, $file_name)
    {
        Storage::delete("/$path/$file_name");
    }
}

if (!function_exists('hotel_discount')) {
    function hotel_discount($convert_to_int = true)
    {
        $setting = Setting::where('meta_key', Setting::HOTEL_DISCOUNT)->first();

        $value = $setting ? (int) $setting->meta_value : 0;

        return $convert_to_int ? $value / 100 : $value;
    }
}

if (!function_exists('ticket_discount')) {
    function ticket_discount($convert_to_int = true)
    {
        $setting = Setting::where('meta_key', Setting::ENTRANCE_TICKET_DISCOUNT)->first();

        $value = $setting ? (int) $setting->meta_value : 0;

        return $convert_to_int ? $value / 100 : $value;
    }
}

if (!function_exists('vantour_discount')) {
    function vantour_discount($convert_to_int = true)
    {
        $setting = Setting::where('meta_key', Setting::PRIVATE_VAN_TOUR_DISCOUNT)->first();

        $value = $setting ? (int) $setting->meta_value : 0;

        return $convert_to_int ? $value / 100 : $value;
    }
}
