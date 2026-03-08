<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function storePreview(Request $request, UploadService $uploads): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'image'],
            'directory' => ['nullable', 'string'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['file'];
        $directory = $validated['directory'] ?? 'previews';

        /** @var Upload $upload */
        $upload = $uploads->storeStandaloneFromFile($file, $directory);

        return response()->json([
            'id' => $upload->id,
            'name' => $upload->name,
            'size' => $upload->size,
            'mime_type' => $upload->mime_type,
            'extension' => $upload->extension,
            'path' => $upload->path,
            'url' => $upload->url,
        ]);
    }
}

