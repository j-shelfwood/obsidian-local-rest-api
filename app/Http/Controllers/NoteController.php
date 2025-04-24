<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

class NoteController extends Controller
{
    protected function parseNote(string $raw): array
    {
        $front = [];
        $content = $raw;

        if (substr($raw, 0, 3) === '---') {
            $parts = explode('---', $raw, 3);
            $front = Yaml::parse(trim($parts[1])) ?: [];
            $content = ltrim($parts[2], "\r\n");
        }

        return ['front_matter' => $front, 'content' => $content];
    }

    protected function buildNote(array $front, string $content): string
    {
        $yaml = Yaml::dump($front);

        return "---\n{$yaml}---\n{$content}";
    }

    public function index()
    {
        $paths = Storage::disk('vault')->allFiles();
        $notes = [];

        foreach ($paths as $path) {
            if (! str_ends_with($path, '.md')) {
                continue;
            }
            $raw = Storage::disk('vault')->get($path);
            extract($this->parseNote($raw));
            $notes[] = ['path' => $path, 'front_matter' => $front_matter, 'content' => $content];
        }

        return response()->json($notes);
    }

    public function search(Request $request)
    {
        $field = $request->query('field');
        $value = $request->query('value');

        if (! $field || ! $value) {
            return response()->json(['error' => 'field and value query parameters are required'], 400);
        }

        $results = [];
        foreach (Storage::disk('vault')->allFiles() as $path) {
            if (! str_ends_with($path, '.md')) {
                continue;
            }
            $raw = Storage::disk('vault')->get($path);
            extract($this->parseNote($raw));

            if (isset($front_matter[$field]) && (string) $front_matter[$field] === (string) $value) {
                $results[] = ['path' => $path, 'front_matter' => $front_matter];
            }
        }

        return response()->json($results);
    }

    public function show(string $path)
    {
        if (! Storage::disk('vault')->exists($path)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        $raw = Storage::disk('vault')->get($path);
        extract($this->parseNote($raw));

        return response()->json(['path' => $path, 'front_matter' => $front_matter, 'content' => $content]);
    }

    public function store(Request $request)
    {
        $path = $request->input('path');
        $front = $request->input('front_matter', []);
        $content = $request->input('content', '');

        if (! $path) {
            return response()->json(['error' => 'path is required'], 400);
        }

        if (! str_ends_with($path, '.md')) {
            $path .= '.md';
        }

        $raw = $this->buildNote($front, $content);
        Storage::disk('vault')->put($path, $raw);

        return response()->json(['message' => 'Note created', 'path' => $path], 201);
    }

    public function update(Request $request, string $path)
    {
        if (! Storage::disk('vault')->exists($path)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        $front = $request->input('front_matter', []);
        $content = $request->input('content', '');
        $raw = $this->buildNote($front, $content);

        Storage::disk('vault')->put($path, $raw);

        return response()->json(['message' => 'Note replaced', 'path' => $path]);
    }

    public function patch(Request $request, string $path)
    {
        if (! Storage::disk('vault')->exists($path)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        $raw = Storage::disk('vault')->get($path);
        $data = $this->parseNote($raw);
        $front = $data['front_matter'];
        $content = $data['content'];

        if ($request->has('front_matter') && is_array($request->input('front_matter'))) {
            $front = array_merge($front, $request->input('front_matter'));
        }

        if ($request->has('content')) {
            $content = $request->input('content');
        }

        $newRaw = $this->buildNote($front, $content);
        Storage::disk('vault')->put($path, $newRaw);

        return response()->json(['message' => 'Note updated', 'path' => $path]);
    }

    public function destroy(string $path)
    {
        if (! Storage::disk('vault')->exists($path)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        Storage::disk('vault')->delete($path);

        return response()->json(['message' => 'Note deleted', 'path' => $path]);
    }

    public function bulkDelete(Request $request)
    {
        $paths = $request->input('paths', []);
        if (! is_array($paths)) {
            return response()->json(['error' => 'paths must be an array'], 400);
        }

        $deleted = [];
        $notFound = [];
        foreach ($paths as $p) {
            if (Storage::disk('vault')->exists($p)) {
                Storage::disk('vault')->delete($p);
                $deleted[] = $p;
            } else {
                $notFound[] = $p;
            }
        }

        return response()->json(compact('deleted', 'notFound'));
    }

    public function bulkUpdate(Request $request)
    {
        $items = $request->input('items', []);
        if (! is_array($items)) {
            return response()->json(['error' => 'items must be an array of objects'], 400);
        }

        $results = [];
        foreach ($items as $item) {
            $p = $item['path'] ?? null;
            if (! $p || ! Storage::disk('vault')->exists($p)) {
                $results[] = ['path' => $p, 'status' => 'not_found'];

                continue;
            }

            $raw = Storage::disk('vault')->get($p);
            $data = $this->parseNote($raw);
            $front = $data['front_matter'];
            $content = $data['content'];

            if (isset($item['front_matter']) && is_array($item['front_matter'])) {
                $front = array_merge($front, $item['front_matter']);
            }

            if (isset($item['content'])) {
                $content = $item['content'];
            }

            $newRaw = $this->buildNote($front, $content);
            Storage::disk('vault')->put($p, $newRaw);
            $results[] = ['path' => $p, 'status' => 'updated'];
        }

        return response()->json(['results' => $results]);
    }
}
