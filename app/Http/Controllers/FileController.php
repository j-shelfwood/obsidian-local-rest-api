<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileStoreRequest;
use App\Http\Requests\FileUpdateRequest;
use App\Http\Resources\FileResource;
use App\Http\Resources\PrimitiveResource;
use App\Services\LocalVaultService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
    public function index(Request $request)
    {
        $files = $this->vault->allFiles();

        return FileResource::collection($files);
    }

    /**
     * Store a newly created file in storage.
     */
    public function store(FileStoreRequest $request)
    {
        $validated = $request->validated();
        $path = $validated['path'];
        $type = $validated['type'] ?? 'file';

        if ($type === 'directory') {
            if ($this->vault->exists($path)) {
                return (new PrimitiveResource([
                    'error' => 'Directory already exists',
                ]))->response()->setStatusCode(409);
            }
            $this->vault->makeDirectory($path);

            return (new PrimitiveResource([
                'message' => 'Directory created successfully',
                'path' => $path,
            ]))->response()->setStatusCode(201);
        }

        $content = $validated['content'] ?? '';

        if ($this->vault->exists($path)) {
            return (new PrimitiveResource([
                'error' => 'File already exists',
            ]))->response()->setStatusCode(409);
        }

        $this->vault->put($path, $content);

        return (new PrimitiveResource([
            'message' => 'File created successfully',
            'path' => $path,
        ]))->response()->setStatusCode(201);
    }

    /**
     * Display the specified file.
     */
    public function show(Request $request, string $path)
    {
        $decodedPath = urldecode($path);

        if (! $this->vault->exists($decodedPath)) {
            return (new PrimitiveResource([
                'error' => 'File not found',
            ]))->response()->setStatusCode(404);
        }

        // If the path is a directory, treat as not found
        $absolutePath = $this->vault->path($decodedPath);
        if (is_dir($absolutePath)) {
            return (new PrimitiveResource([
                'error' => 'File not found',
            ]))->response()->setStatusCode(404);
        }

        $content = $this->vault->get($decodedPath);
        // $mimeType = file_exists($absolutePath) ? mime_content_type($absolutePath) : null; // Mime type can be added if needed

        // Return JSON response
        return new PrimitiveResource([
            'path' => $decodedPath,
            'content' => $content,
            // 'mimeType' => $mimeType ?: 'text/plain', // Optionally include mimeType
        ]);
    }

    /**
     * Update the specified file in storage.
     */
    public function update(FileUpdateRequest $request, string $path)
    {
        $validated = $request->validated();
        $content = $validated['content'];
        $decodedPath = urldecode($path);

        if (! $this->vault->exists($decodedPath)) {
            return (new PrimitiveResource([
                'error' => 'File not found',
            ]))->response()->setStatusCode(404);
        }

        $this->vault->put($decodedPath, $content);

        return new PrimitiveResource([
            'message' => 'File updated successfully',
            'path' => $decodedPath,
        ]);
    }

    /**
     * Remove the specified file from storage.
     */
    public function destroy(Request $request, string $path)
    {
        $decodedPath = urldecode($path);

        if (! $this->vault->exists($decodedPath)) {
            return (new PrimitiveResource([
                'error' => 'File not found',
            ]))->response()->setStatusCode(404);
        }

        $absolutePath = $this->vault->path($decodedPath);

        if (is_dir($absolutePath)) {
            // Delete directory
            \Illuminate\Support\Facades\Storage::disk('vault')->deleteDirectory($decodedPath);

            return new PrimitiveResource([
                'message' => 'Directory deleted successfully',
                'path' => $decodedPath,
            ]);
        }
        // Delete file
        $this->vault->delete($decodedPath);

        return new PrimitiveResource([
            'message' => 'File deleted successfully',
            'path' => $decodedPath,
        ]);
    }
}
