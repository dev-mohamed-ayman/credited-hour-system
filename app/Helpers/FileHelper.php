<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileHelper
{
    public static function upload(UploadedFile $file, string $path, $oldFile = null, string $disk = 'public')
    {

        try {
            if ($oldFile) {
                Storage::disk($disk)->delete($oldFile);
            }

            $filename = Str::uuid().'.'.$file->getClientOriginalExtension();

            $filePath = $file->storeAs('uploads/'.$path, $filename, $disk);

            return $filePath;
        } catch (\Exception $exception) {
            Log::error('File upload error: '.$exception->getMessage());

            return null;
        }
    }

    public static function delete(string $path, string $disk = 'public')
    {
        if (! $path) {
            return false;
        }

        try {
            return Storage::disk($disk)->delete($path);
        } catch (\Exception $exception) {
            Log::error('File delete error: '.$exception->getMessage());

            return false;
        }
    }
}
