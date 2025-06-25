<?php

namespace App\Http\Controllers;

use App\Http\Resources\NoteResource;
use App\Http\Resources\PrimitiveResource;
use App\Models\Note;
use App\Services\LocalVaultService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\Yaml\Yaml;

class VaultController extends Controller
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
                    $front = [];
                }
                $content = ltrim($parts[2], "\r\n");
            } elseif (count($parts) >= 2) {
                $content = ltrim(implode('---', array_slice($parts, 2)), "\r\n");
            }
        }

        return ['front_matter' => $front, 'content' => $content];
    }

    /**
     * List directory contents with pagination and filtering
     */
    public function listDirectory(Request $request)
    {
        $path = $request->get('path', '.');
        $recursive = $request->boolean('recursive', false);
        $limit = $request->integer('limit', 50);
        $offset = $request->integer('offset', 0);

        try {
            $allFiles = collect($this->vault->files($path, $recursive));
            $totalItems = $allFiles->count();

            $files = $allFiles
                ->skip($offset)
                ->take($limit)
                ->map(function ($filePath) {
                    return [
                        'path' => $filePath,
                        'type' => $this->vault->isDirectory($filePath) ? 'directory' : 'file',
                        'size' => $this->vault->isDirectory($filePath) ? null : $this->vault->size($filePath),
                        'modified' => $this->vault->lastModified($filePath),
                    ];
                })
                ->values();

            return new PrimitiveResource([
                'items' => $files->toArray(),
                'total_items' => $totalItems,
                'path' => $path,
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => ($offset + $limit) < $totalItems,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Directory not found or access denied'], 404);
        }
    }

    /**
     * Search vault content across files, filenames, and metadata
     */
    public function searchVault(Request $request)
    {
        $query = $request->get('query');
        $scope = $request->get('scope', ['content', 'filename', 'tags']);
        $pathFilter = $request->get('path_filter');
        $limit = $request->integer('limit', 20);

        if (empty($query)) {
            return response()->json(['error' => 'Query parameter is required'], 400);
        }

        $results = collect();
        $searchPattern = strtolower($query);

        // Get all markdown files
        $files = collect($this->vault->files('.', true))
            ->filter(fn ($path) => str($path)->endsWith('.md'));

        // Apply path filter if specified
        if ($pathFilter) {
            $files = $files->filter(fn ($path) => str($path)->startsWith($pathFilter));
        }

        foreach ($files->take($limit * 2) as $filePath) { // Take more to account for filtering
            $matches = [];
            $shouldInclude = false;

            // Search filename
            if (in_array('filename', $scope) && str_contains(strtolower($filePath), $searchPattern)) {
                $matches[] = 'filename';
                $shouldInclude = true;
            }

            try {
                $content = $this->vault->get($filePath);
                $parsed = $this->parseNote($content);

                // Search content
                if (in_array('content', $scope) && str_contains(strtolower($parsed['content']), $searchPattern)) {
                    $matches[] = 'content';
                    $shouldInclude = true;
                }

                // Search tags in frontmatter
                if (in_array('tags', $scope) && ! empty($parsed['front_matter']['tags'])) {
                    $tags = is_array($parsed['front_matter']['tags'])
                        ? $parsed['front_matter']['tags']
                        : [$parsed['front_matter']['tags']];

                    foreach ($tags as $tag) {
                        if (str_contains(strtolower($tag), $searchPattern)) {
                            $matches[] = 'tags';
                            $shouldInclude = true;
                            break;
                        }
                    }
                }

                if ($shouldInclude) {
                    $note = Note::fromParsed($filePath, $parsed);
                    $results->push([
                        'note' => $note,
                        'matches' => $matches,
                        'relevance' => count($matches), // Simple relevance scoring
                    ]);
                }
            } catch (\Exception $e) {
                // Skip files that can't be read
                continue;
            }

            if ($results->count() >= $limit) {
                break;
            }
        }

        // Sort by relevance
        $results = $results->sortByDesc('relevance')->take($limit);

        return new PrimitiveResource([
            'results' => $results->map(fn ($result) => [
                'note' => new NoteResource($result['note']),
                'matches' => $result['matches'],
                'relevance' => $result['relevance'],
            ])->values(),
            'query' => $query,
            'scope' => $scope,
            'total_results' => $results->count(),
        ]);
    }

    /**
     * Get recent notes based on modification time
     */
    public function getRecentNotes(Request $request)
    {
        $limit = $request->integer('limit', 5);

        $recentNotes = collect($this->vault->files('.', true))
            ->filter(fn ($path) => str($path)->endsWith('.md'))
            ->map(function ($path) {
                try {
                    return [
                        'path' => $path,
                        'modified' => $this->vault->lastModified($path),
                        'content' => $this->vault->get($path),
                    ];
                } catch (\Exception $e) {
                    return null;
                }
            })
            ->filter()
            ->sortByDesc('modified')
            ->take($limit)
            ->map(function ($file) {
                $note = Note::fromParsed($file['path'], $this->parseNote($file['content']));

                return $note;
            });

        return NoteResource::collection($recentNotes);
    }

    /**
     * Get daily note for a specific date
     */
    public function getDailyNote(Request $request)
    {
        $date = $request->get('date', 'today');

        // Parse date string
        try {
            if ($date === 'today') {
                $carbon = Carbon::today();
            } elseif ($date === 'yesterday') {
                $carbon = Carbon::yesterday();
            } elseif ($date === 'tomorrow') {
                $carbon = Carbon::tomorrow();
            } else {
                $carbon = Carbon::parse($date);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid date format'], 400);
        }

        // Common daily note naming patterns
        $dateFormats = [
            $carbon->format('Y-m-d'),           // 2024-05-21
            $carbon->format('Y-m-d').'.md',   // 2024-05-21.md
            'Daily Notes/'.$carbon->format('Y-m-d').'.md',
            'daily/'.$carbon->format('Y-m-d').'.md',
            $carbon->format('Y/m/d').'.md',   // 2024/05/21.md
        ];

        foreach ($dateFormats as $possiblePath) {
            if ($this->vault->exists($possiblePath)) {
                try {
                    $content = $this->vault->get($possiblePath);
                    $note = Note::fromParsed($possiblePath, $this->parseNote($content));

                    return new NoteResource($note);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return response()->json([
            'error' => 'Daily note not found',
            'date' => $carbon->format('Y-m-d'),
            'searched_paths' => $dateFormats,
        ], 404);
    }

    /**
     * Find notes related to a given note based on tags and links
     */
    public function getRelatedNotes(Request $request, string $path)
    {
        $decodedPath = urldecode($path);
        $on = $request->get('on', ['tags', 'links']);
        $limit = $request->integer('limit', 10);

        if (! $this->vault->exists($decodedPath)) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        try {
            $content = $this->vault->get($decodedPath);
            $parsed = $this->parseNote($content);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Could not read note'], 500);
        }

        $relatedNotes = collect();
        $noteTags = [];
        $noteLinks = [];

        // Extract tags from frontmatter
        if (in_array('tags', $on) && ! empty($parsed['front_matter']['tags'])) {
            $noteTags = is_array($parsed['front_matter']['tags'])
                ? $parsed['front_matter']['tags']
                : [$parsed['front_matter']['tags']];
        }

        // Extract wikilinks from content
        if (in_array('links', $on)) {
            preg_match_all('/\[\[([^\]]+)\]\]/', $parsed['content'], $matches);
            $noteLinks = $matches[1] ?? [];
        }

        // Search through all notes
        $allNotes = collect($this->vault->files('.', true))
            ->filter(fn ($notePath) => str($notePath)->endsWith('.md') && $notePath !== $decodedPath);

        foreach ($allNotes as $notePath) {
            try {
                $noteContent = $this->vault->get($notePath);
                $noteParsed = $this->parseNote($noteContent);
                $similarity = 0;
                $connections = [];

                // Check tag similarity
                if (! empty($noteTags) && ! empty($noteParsed['front_matter']['tags'])) {
                    $otherTags = is_array($noteParsed['front_matter']['tags'])
                        ? $noteParsed['front_matter']['tags']
                        : [$noteParsed['front_matter']['tags']];

                    $sharedTags = array_intersect($noteTags, $otherTags);
                    if (! empty($sharedTags)) {
                        $similarity += count($sharedTags);
                        $connections[] = 'shared_tags: '.implode(', ', $sharedTags);
                    }
                }

                // Check link connections
                if (! empty($noteLinks)) {
                    $noteBasename = pathinfo($notePath, PATHINFO_FILENAME);
                    if (in_array($noteBasename, $noteLinks)) {
                        $similarity += 2; // Links are stronger connections
                        $connections[] = 'linked_to';
                    }
                }

                // Check if this note links back to the original
                preg_match_all('/\[\[([^\]]+)\]\]/', $noteParsed['content'], $backlinks);
                $originalBasename = pathinfo($decodedPath, PATHINFO_FILENAME);
                if (in_array($originalBasename, $backlinks[1] ?? [])) {
                    $similarity += 2;
                    $connections[] = 'links_back';
                }

                if ($similarity > 0) {
                    $note = Note::fromParsed($notePath, $noteParsed);
                    $relatedNotes->push([
                        'note' => $note,
                        'similarity' => $similarity,
                        'connections' => $connections,
                    ]);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Sort by similarity and take top results
        $relatedNotes = $relatedNotes
            ->sortByDesc('similarity')
            ->take($limit);

        return new PrimitiveResource([
            'related_notes' => $relatedNotes->map(fn ($result) => [
                'note' => new NoteResource($result['note']),
                'similarity' => $result['similarity'],
                'connections' => $result['connections'],
            ])->values(),
            'source_note' => $decodedPath,
            'criteria' => $on,
            'total_found' => $relatedNotes->count(),
        ]);
    }
}
