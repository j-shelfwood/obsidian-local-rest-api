<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileStoreRequest;
use App\Http\Requests\FileUpdateRequest;
use App\Services\LocalVaultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    protected LocalVaultService $vault;

    public function __construct(LocalVaultService $vault)
    {
        $this->vault = $vault;
    }

    /**
     * Display a listing of the files.
     */
    public function index(): JsonResponse
    {
        $files = $this->vault->allFiles();
        return response()->json($files);
    }

    /**
     * Store a newly created file in storage.
     */
    public function store(FileStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $path = $validated['path'];
        $type = $validated['type'] ?? 'file';

        if ($type === 'directory') {
            if ($this->vault->exists($path)) {
                return response()->json(['error' => 'Directory already exists'], 409);
            }
            $this->vault->makeDirectory($path);

            return response()->json(['message' => 'Directory created successfully', 'path' => $path], 201);
        }

        $content = $validated['content'] ?? '';

        if ($this->vault->exists($path)) {
            return response()->json(['error' => 'File already exists'], 409);
        }

        $this->vault->put($path, $content);

        return response()->json(['message' => 'File created successfully', 'path' => $path], 201);
    }

    /**
     * Display the specified file.
     */
    public function show(string $path): Response|JsonResponse|StreamedResponse
    {
        $decodedPath = urldecode($path);

        if (! $this->vault->exists($decodedPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // If the path is a directory, treat as not found
        $absolutePath = $this->vault->path($decodedPath);
        if (is_dir($absolutePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $content = $this->vault->get($decodedPath);
        // Use mime_content_type with the absolute path
        $mimeType = file_exists($absolutePath) ? mime_content_type($absolutePath) : null;

        // Return raw content with appropriate mime type
        return response($content, 200)->header('Content-Type', $mimeType ?: 'text/plain');
    }

    /**
     * Update the specified file in storage.
     */
    public function update(FileUpdateRequest $request, string $path): JsonResponse
    {
        $validated = $request->validated();
        $content = $validated['content'];
        $decodedPath = urldecode($path);

        if (! $this->vault->exists($decodedPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $this->vault->put($decodedPath, $content);

        return response()->json(['message' => 'File updated successfully', 'path' => $decodedPath]);
    }

    /**
     * Remove the specified file from storage.
     */
    public function destroy(string $path): JsonResponse
    {
        $decodedPath = urldecode($path);

        if (! $this->vault->exists($decodedPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $this->vault->delete($decodedPath);

        return response()->json(['message' => 'File deleted successfully']);
    }
}
