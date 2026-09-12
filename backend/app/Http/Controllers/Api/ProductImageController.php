<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageController extends Controller
{
    private const DIRECTORY = 'product-images';

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Four megabytes is plenty for a listing photo and keeps a stray
            // 40MP phone upload from filling the disk.
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ]);

        $file = $request->file('image');
        // Never trust the client's filename: generate our own and keep only
        // the extension, which validation has already constrained.
        $name = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension());

        $file->storeAs(self::DIRECTORY, $name, 'public');

        return response()->json([
            'path' => self::DIRECTORY.'/'.$name,
            'url' => url('/api/images/'.self::DIRECTORY.'/'.$name),
        ], 201);
    }

    /**
     * Images are streamed through the app rather than served from a
     * public/storage symlink. `storage:link` is awkward across the Docker bind
     * mount on Windows, and this behaves identically everywhere. It stays
     * unauthenticated because an <img> tag cannot send a bearer token.
     */
    public function show(string $path): StreamedResponse
    {
        // Only ever serve from the upload directory, whatever the URL claims.
        abort_unless(Str::startsWith($path, self::DIRECTORY.'/'), 404);
        abort_if(Str::contains($path, ['..', "\0"]), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
