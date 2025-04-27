<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\BulkUpdateRequest;
use App\Http\Requests\NotePatchRequest;
use App\Http\Requests\NoteStoreRequest;
use App\Http\Requests\NoteUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

class NoteController extends Controller
{
    protected function parseNote(string $raw): array
    {
        $front = [];
        $content = $raw;

        if (str($raw)->startsWith('---')) {
            $parts = explode('---', $raw, 3);
            if (count($parts) === 3 && trim($parts[1]) !== '') {
                try {
                    $front = Yaml::parse(trim($parts[1])) ?: [];
                } catch (\Symfony\Component\Yaml\Exception\ParseException $e) {
                    // Handle or log parse error if necessary, default to empty
                    $front = [];
                }
                $content = ltrim($parts[2], "\r\n");
            } elseif (count($parts) >= 2) {
                // Handle case where there might be frontmatter markers but no content or invalid structure
                $content = ltrim(implode('---', array_slice($parts, 2)), "\r\n");
            }
        }

        return ['front_matter' => $front, 'content' => $content];
    }

    protected function buildNote(array $front, string $content): string
    {
        // Only add frontmatter block if there's actual frontmatter
        if (empty($front)) {
            return $content;
        }

        $yaml = Yaml::dump($front);

        return "---\n{$yaml}---\n{$content}";
    }

    public function index(): JsonResponse
    {
        $notes = collect(Storage::disk('vault')->files('.', true))
            ->filter(fn ($path) => str($path)->endsWith('.md'))
            ->map(fn ($path) => ['path' => $path] + $this->parseNote(Storage::disk('vault')->get($path)))
            ->values()
            ->all();

        return response()->json($notes);
    }

    public function show(string $path): JsonResponse
    {
        $decodedPath = str($path)->toString(); // preserve path
        if (! Storage::disk('vault')->exists($decodedPath)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        $raw = Storage::disk('vault')->get($decodedPath);
        $data = $this->parseNote($raw);

        return response()->json(['path' => $decodedPath, 'front_matter' => $data['front_matter'], 'content' => $data['content']]);
    }

    public function store(NoteStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $path = $validated['path'];
        $front = $validated['front_matter'] ?? [];
        $content = $validated['content'] ?? '';

        // Ensure path ends with .md
        if (! str_ends_with($path, '.md')) {
            $path .= '.md';
        }

        if (Storage::disk('vault')->exists($path)) {
            return response()->json(['error' => 'Note already exists at this path'], 409);
        }

        $raw = $this->buildNote($front, $content);
        Storage::disk('vault')->put($path, $raw);

        $responseData = $this->parseNote($raw);
        $responseData['path'] = $path;

        return response()->json($responseData, 201);
    }

    public function update(NoteUpdateRequest $request, string $path): JsonResponse
    {
        $decodedPath = urldecode($path);
        if (! Storage::disk('vault')->exists($decodedPath)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        $validated = $request->validated();
        $front = $validated['front_matter'] ?? [];
        $content = $validated['content'] ?? '';
        $raw = $this->buildNote($front, $content);

        Storage::disk('vault')->put($decodedPath, $raw);

        $responseData = $this->parseNote($raw);
        $responseData['path'] = $decodedPath;

        return response()->json($responseData);
    }

    public function patch(NotePatchRequest $request, string $path): JsonResponse
    {
        $decodedPath = urldecode($path);
        if (! Storage::disk('vault')->exists($decodedPath)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        $raw = Storage::disk('vault')->get($decodedPath);
        $data = $this->parseNote($raw);
        $front = $data['front_matter'];
        $content = $data['content'];

        $validated = $request->validated();

        if (isset($validated['front_matter'])) {
            $front = array_merge($front, $validated['front_matter']);
        }

        if (isset($validated['content'])) {
            $content = $validated['content'];
        }

        $newRaw = $this->buildNote($front, $content);
        Storage::disk('vault')->put($decodedPath, $newRaw);

        $responseData = $this->parseNote($newRaw);
        $responseData['path'] = $decodedPath;

        return response()->json($responseData);
    }

    public function destroy(string $path): JsonResponse
    {
        $decodedPath = urldecode($path);
        if (! Storage::disk('vault')->exists($decodedPath)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        Storage::disk('vault')->delete($decodedPath);

        // Return 204 No Content on successful deletion
        return response()->json(null, 204);
    }

    public function bulkDelete(BulkDeleteRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $paths = $validated['paths'];

        $results = collect($paths)
            ->map(function ($path) {
                $decoded = urldecode($path);

                return Storage::disk('vault')->exists($decoded)
                    ? ['path' => $decoded, 'status' => 'deleted']
                    : ['path' => $decoded, 'status' => 'not_found'];
            })
            ->partition(fn ($item) => $item['status'] === 'deleted');

        return response()->json([
            'deleted' => $results[0]->pluck('path')->values()->all(),
            'notFound' => $results[1]->pluck('path')->values()->all(),
        ]);
    }

    public function bulkUpdate(BulkUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $items = $validated['items'];

        $results = collect($items)
            ->map(function ($item) {
                $decoded = urldecode($item['path']);
                if (! Storage::disk('vault')->exists($decoded)) {
                    return ['path' => $decoded, 'status' => 'not_found'];
                }
                $data = $this->parseNote(Storage::disk('vault')->get($decoded));
                $front = array_merge($data['front_matter'], $item['front_matter'] ?? []);
                $content = $item['content'] ?? $data['content'];
                $raw = $this->buildNote($front, $content);
                Storage::disk('vault')->put($decoded, $raw);
                $note = $this->parseNote($raw) + ['path' => $decoded];

                return ['path' => $decoded, 'status' => 'updated', 'note' => $note];
            });

        return response()->json(['results' => $results->values()->all()]);
    }
}
