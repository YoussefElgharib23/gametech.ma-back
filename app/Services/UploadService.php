<?php

namespace App\Services;

use App\Models\Upload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class UploadService
{
    /**
     * Store an uploaded file on the public disk and attach it to a model.
     */
    public function storeFromFile(
        UploadedFile $file,
        Model $model,
        string $directory = 'uploads',
        string $relation = 'image',
    ): Upload {
        $storedPath = $file->store($directory, 'public');

        $upload = new Upload([
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'path' => $storedPath,
        ]);

        $model->{$relation}()->save($upload);

        return $upload;
    }

    /**
     * Store an uploaded file on the public disk without attaching it to a model.
     *
     * Useful for standalone uploads (e.g. previews) that can be linked later.
     */
    public function storeStandaloneFromFile(
        UploadedFile $file,
        string $directory = 'uploads',
    ): Upload {
        $storedPath = $file->store($directory, 'public');

        return Upload::create([
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'path' => $storedPath,
        ]);
    }

    /**
     * Download a file from a URL, store it on the public disk and attach it to a model.
     */
    public function storeFromUrl(
        string $url,
        Model $model,
        string $directory = 'uploads',
        string $relation = 'image',
    ): Upload {
        $response = Http::get($url);
        $response->throw();

        $contents = $response->body();

        $name = basename(parse_url($url, PHP_URL_PATH) ?: 'file');
        $extension = pathinfo($name, PATHINFO_EXTENSION) ?: null;

        $filename = uniqid(pathinfo($name, PATHINFO_FILENAME) . '_') . ($extension ? ".{$extension}" : '');
        $path = trim($directory, '/') . '/' . $filename;

        Storage::disk('public')->put($path, $contents);

        $size = Storage::disk('public')->size($path);

        // Infer mime type using PHP's finfo to avoid static analysis issues.
        $mimeType = null;
        $fullPath = Storage::disk('public')->path($path);
        if (is_file($fullPath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $fullPath);
                if (is_string($detected)) {
                    $mimeType = $detected;
                }
                finfo_close($finfo);
            }
        }

        $upload = new Upload([
            'name' => $name,
            'size' => $size,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'path' => $path,
        ]);

        $model->{$relation}()->save($upload);

        return $upload;
    }
}

