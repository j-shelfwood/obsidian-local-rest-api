<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkDeleteRequest;
use App\Http\Requests\BulkUpdateRequest;
use App\Http\Requests\NotePatchRequest;
use App\Http\Requests\NoteStoreRequest;
use App\Http\Requests\NoteUpdateRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Services\LocalVaultService;
use Illuminate\Http\Request;
use Symfony\Component\Yaml\Yaml;

class NoteController extends Controller
{
    protected LocalVaultService $vault;

    public function __construct(LocalVaultService $vault)
    {
        $this->vault = $vault;
    }

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

    public function index(Request $request)
    {
        $search = $request->get('search');

        $notes = collect($this->vault->files('.', true))
            ->filter(fn ($path) => str($path)->endsWith('.md'))
            ->map(fn ($path) => Note::fromParsed($path, $this->parseNote($this->vault->get($path))));

        // Apply search filter if provided
        if ($search) {
            $searchLower = strtolower($search);
            $notes = $notes->filter(function ($note) use ($searchLower) {
                return str_contains(strtolower($note->path), $searchLower)
                    || str_contains(strtolower($note->content), $searchLower)
                    || (! empty($note->front_matter['tags']) &&
                        collect($note->front_matter['tags'])
                            ->contains(fn ($tag) => str_contains(strtolower($tag), $searchLower)));
            });
        }

        return NoteResource::collection($notes->values());
    }

    public function show(string $path)
    {
        $decodedPath = urldecode($path);
        if (! $this->vault->exists($decodedPath)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        $note = Note::fromParsed($decodedPath, $this->parseNote($this->vault->get($decodedPath)));

        return new NoteResource($note);
    }

    public function store(NoteStoreRequest $request)
    {
        $validated = $request->validated();
        $path = $validated['path'];
        if (! str($path)->endsWith('.md')) {
            $path .= '.md';
        }
        if ($this->vault->exists($path)) {
            return response()->json(['error' => 'Note already exists at this path'], 409);
        }
        $raw = $this->buildNote($validated['front_matter'] ?? [], $validated['content'] ?? '');
        $this->vault->put($path, $raw);
        $note = Note::fromParsed($path, $this->parseNote($raw));

        return (new NoteResource($note))->response()->setStatusCode(201);
    }

    /**
     * Create or update a note (upsert operation)
     */
    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'path' => 'required|string',
            'content' => 'string',
            'front_matter' => 'array',
        ]);

        $path = $validated['path'];
        if (! str($path)->endsWith('.md')) {
            $path .= '.md';
        }

        $exists = $this->vault->exists($path);
        $raw = $this->buildNote($validated['front_matter'] ?? [], $validated['content'] ?? '');
        $this->vault->put($path, $raw);
        $note = Note::fromParsed($path, $this->parseNote($raw));

        $response = new NoteResource($note);

        return $exists
            ? $response  // 200 for update
            : $response->response()->setStatusCode(201); // 201 for create
    }

    public function update(NoteUpdateRequest $request, string $path)
    {
        $decodedPath = urldecode($path);
        if (! $this->vault->exists($decodedPath)) {
            return response()->json(['error' => 'Note not found'], 404);
        }
        $raw = $this->buildNote($request->validated()['front_matter'] ?? [], $request->validated()['content'] ?? '');
        $this->vault->put($decodedPath, $raw);
        $note = Note::fromParsed($decodedPath, $this->parseNote($raw));

        return new NoteResource($note);
    }

    public function patch(NotePatchRequest $request, string $path)
    {
        $decodedPath = urldecode($path);
        if (! $this->vault->exists($decodedPath)) {
            return response()->json(['error' => 'Note not found'], 404);
        }
        $data = $this->parseNote($this->vault->get($decodedPath));
        $front = array_merge($data['front_matter'], $request->validated()['front_matter'] ?? []);
        $content = $request->validated()['content'] ?? $data['content'];
        $newRaw = $this->buildNote($front, $content);
        $this->vault->put($decodedPath, $newRaw);
        $note = Note::fromParsed($decodedPath, $this->parseNote($newRaw));

        return new NoteResource($note);
    }

    public function destroy(string $path)
    {
        $decodedPath = urldecode($path);
        if (! $this->vault->exists($decodedPath)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        $this->vault->delete($decodedPath);

        // Return 204 No Content on successful deletion
        return response()->json(null, 204);
    }

    public function bulkDelete(BulkDeleteRequest $request)
    {
        $validated = $request->validated();

        $paths = $validated['paths'];

        $results = collect($paths)
            ->map(function ($path) {
                $decoded = urldecode($path);

                return $this->vault->exists($decoded)
                    ? ['path' => $decoded, 'status' => 'deleted']
                    : ['path' => $decoded, 'status' => 'not_found'];
            })
            ->partition(fn ($item) => $item['status'] === 'deleted');

        return response()->json([
            'deleted' => $results[0]->pluck('path')->values()->all(),
            'notFound' => $results[1]->pluck('path')->values()->all(),
        ]);
    }

    public function bulkUpdate(BulkUpdateRequest $request)
    {
        $validated = $request->validated();

        $items = $validated['items'];

        $results = collect($items)
            ->map(function ($item) {
                $decoded = urldecode($item['path']);
                if (! $this->vault->exists($decoded)) {
                    return ['path' => $decoded, 'status' => 'not_found'];
                }
                $data = $this->parseNote($this->vault->get($decoded));
                $front = array_merge($data['front_matter'], $item['front_matter'] ?? []);
                $content = $item['content'] ?? $data['content'];
                $raw = $this->buildNote($front, $content);
                $this->vault->put($decoded, $raw);
                $note = $this->parseNote($raw) + ['path' => $decoded];

                return ['path' => $decoded, 'status' => 'updated', 'note' => $note];
            });

        return response()->json(['results' => $results->values()->all()]);
    }
}
