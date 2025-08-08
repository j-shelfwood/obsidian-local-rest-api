<?php

namespace App\Http\Controllers;

use App\Http\Resources\PrimitiveResource;
use App\Services\LocalVaultService;
use Illuminate\Http\Request;

/**
 * Practical agent tools for efficient vault operations
 * Focused on real utility, not theoretical AI features
 */
class AgentToolsController extends Controller
{
    protected LocalVaultService $vault;

    public function __construct(LocalVaultService $vault)
    {
        $this->vault = $vault;
    }

    /**
     * Search vault content with regex and patterns - like grep but vault-aware
     */
    public function grepVault(Request $request)
    {
        $validated = $request->validate([
            'pattern' => 'required|string',
            'is_regex' => 'boolean',
            'case_sensitive' => 'boolean',
            'include_frontmatter' => 'boolean',
            'file_pattern' => 'string',
            'max_results' => 'integer|min:1|max:1000',
            'context_lines' => 'integer|min:0|max:10',
        ]);

        $pattern = $validated['pattern'];
        $isRegex = $validated['is_regex'] ?? false;
        $caseSensitive = $validated['case_sensitive'] ?? false;
        $includeFrontmatter = $validated['include_frontmatter'] ?? true;
        $filePattern = $validated['file_pattern'] ?? '*.md';
        $maxResults = $validated['max_results'] ?? 100;
        $contextLines = $validated['context_lines'] ?? 2;

        $files = $this->vault->files('.', true);
        $matchingFiles = array_filter($files, fn ($file) => fnmatch($filePattern, $file));

        $results = [];
        $totalMatches = 0;

        foreach ($matchingFiles as $file) {
            if ($totalMatches >= $maxResults) {
                break;
            }

            try {
                $content = $this->vault->get($file);
                $lines = explode("\n", $content);

                $matches = $this->findMatches($lines, $pattern, $isRegex, $caseSensitive, $includeFrontmatter);

                if (! empty($matches)) {
                    $results[] = [
                        'file' => $file,
                        'matches' => array_slice($matches, 0, $maxResults - $totalMatches),
                        'total_matches' => count($matches),
                    ];
                    $totalMatches += count($matches);
                }
            } catch (\Exception $e) {
                // Skip files that can't be read
                continue;
            }
        }

        return new PrimitiveResource([
            'pattern' => $pattern,
            'is_regex' => $isRegex,
            'files_searched' => count($matchingFiles),
            'files_with_matches' => count($results),
            'total_matches' => $totalMatches,
            'results' => $results,
        ]);
    }

    /**
     * Query frontmatter fields like a database - similar to Dataview plugin
     */
    public function queryFrontmatter(Request $request)
    {
        $validated = $request->validate([
            'fields' => 'array',
            'where' => 'array',
            'sort_by' => 'string',
            'sort_direction' => 'string|in:asc,desc',
            'limit' => 'integer|min:1|max:1000',
            'distinct' => 'boolean',
        ]);

        $fields = $validated['fields'] ?? ['*'];
        $where = $validated['where'] ?? [];
        $sortBy = $validated['sort_by'] ?? null;
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $limit = $validated['limit'] ?? 100;
        $distinct = $validated['distinct'] ?? false;

        $files = $this->vault->files('.', true);
        $markdownFiles = array_filter($files, fn ($file) => str_ends_with($file, '.md'));

        $records = [];

        foreach ($markdownFiles as $file) {
            try {
                $content = $this->vault->get($file);
                $frontmatter = $this->extractFrontmatter($content);

                if (! empty($frontmatter) && $this->matchesWhere($frontmatter, $where)) {
                    $record = ['_file' => $file, '_path' => dirname($file)];

                    if (in_array('*', $fields)) {
                        $record = array_merge($record, $frontmatter);
                    } else {
                        foreach ($fields as $field) {
                            $record[$field] = $frontmatter[$field] ?? null;
                        }
                    }

                    $records[] = $record;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Apply sorting
        if ($sortBy && isset($records[0][$sortBy])) {
            usort($records, function ($a, $b) use ($sortBy, $sortDirection) {
                $aVal = $a[$sortBy] ?? '';
                $bVal = $b[$sortBy] ?? '';
                $comparison = $aVal <=> $bVal;

                return $sortDirection === 'desc' ? -$comparison : $comparison;
            });
        }

        // Apply distinct
        if ($distinct && ! empty($fields) && ! in_array('*', $fields)) {
            $seen = [];
            $records = array_filter($records, function ($record) use ($fields, &$seen) {
                $key = json_encode(array_intersect_key($record, array_flip($fields)));
                if (in_array($key, $seen)) {
                    return false;
                }
                $seen[] = $key;

                return true;
            });
        }

        // Apply limit
        $records = array_slice($records, 0, $limit);

        return new PrimitiveResource([
            'query' => [
                'fields' => $fields,
                'where' => $where,
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
                'limit' => $limit,
                'distinct' => $distinct,
            ],
            'total_files_scanned' => count($markdownFiles),
            'total_records' => count($records),
            'records' => $records,
        ]);
    }

    /**
     * Find notes that link to or from a specific note
     */
    public function getBacklinks(Request $request, string $note_path = null)
    {
        // Prefer route param; fallback to body for backward compatibility
        $routeNotePath = $note_path ?? $request->route('note_path');
        $bodyNotePath = $request->input('note_path');
        $notePath = $routeNotePath ?: $bodyNotePath;

        $validated = $request->validate([
            'include_mentions' => 'boolean',
            'include_tags' => 'boolean',
        ]);

        if (! $notePath) {
            return response()->json(['message' => 'The note_path field is required.'], 422);
        }

        $includeMentions = $validated['include_mentions'] ?? true;
        $includeTags = $validated['include_tags'] ?? false;

        // Ensure .md extension
        if (! str_ends_with($notePath, '.md')) {
            $notePath .= '.md';
        }

        $files = $this->vault->files('.', true);
        $markdownFiles = array_filter($files, fn ($file) => str_ends_with($file, '.md'));

        $backlinks = [];
        $outgoingLinks = [];
        $noteBasename = pathinfo($notePath, PATHINFO_FILENAME);

        // Get outgoing links from the target note
        try {
            $targetContent = $this->vault->get($notePath);
            $outgoingLinks = $this->extractLinks($targetContent, $includeTags);
        } catch (\Exception $e) {
            // Note doesn't exist
        }

        // Find backlinks
        foreach ($markdownFiles as $file) {
            if ($file === $notePath) {
                continue;
            }

            try {
                $content = $this->vault->get($file);
                $links = $this->extractLinks($content, $includeTags);
                $mentions = $includeMentions ? $this->findMentions($content, $noteBasename) : [];

                if (! empty($links) || ! empty($mentions)) {
                    $linkMatches = array_filter($links, function ($link) use ($notePath, $noteBasename) {
                        return $link['target'] === $notePath ||
                               $link['target'] === $noteBasename ||
                               $link['target'] === $noteBasename.'.md';
                    });

                    if (! empty($linkMatches) || ! empty($mentions)) {
                        $backlinks[] = [
                            'file' => $file,
                            'links' => $linkMatches,
                            'mentions' => $mentions,
                            'link_count' => count($linkMatches),
                            'mention_count' => count($mentions),
                        ];
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return new PrimitiveResource([
            'target_note' => $notePath,
            'backlinks' => $backlinks,
            'outgoing_links' => $outgoingLinks,
            'backlink_count' => count($backlinks),
            'outgoing_link_count' => count($outgoingLinks),
        ]);
    }

    /**
     * Extract all unique tags from the vault
     */
    public function getTags(Request $request)
    {
        $validated = $request->validate([
            'min_count' => 'integer|min:1',
            'include_nested' => 'boolean',
            'format' => 'string|in:flat,hierarchical',
        ]);

        $minCount = $validated['min_count'] ?? 1;
        $includeNested = $validated['include_nested'] ?? true;
        $format = $validated['format'] ?? 'flat';

        $files = $this->vault->files('.', true);
        $markdownFiles = array_filter($files, fn ($file) => str_ends_with($file, '.md'));

        $tagCounts = [];

        foreach ($markdownFiles as $file) {
            try {
                $content = $this->vault->get($file);
                $tags = $this->extractAllTags($content, $includeNested);

                foreach ($tags as $tag) {
                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Filter by minimum count
        $tagCounts = array_filter($tagCounts, fn ($count) => $count >= $minCount);

        // Sort by count descending
        arsort($tagCounts);

        $result = [
            'total_unique_tags' => count($tagCounts),
            'total_files_scanned' => count($markdownFiles),
        ];

        if ($format === 'hierarchical') {
            $result['tags'] = $this->buildTagHierarchy($tagCounts);
        } else {
            $result['tags'] = array_map(fn ($tag, $count) => [
                'tag' => $tag,
                'count' => $count,
            ], array_keys($tagCounts), $tagCounts);
        }

        return new PrimitiveResource($result);
    }

    /**
     * Get vault statistics and health metrics
     */
    public function getVaultStats(Request $request)
    {
        $files = $this->vault->files('.', true);

        $stats = [
            'total_files' => count($files),
            'markdown_files' => 0,
            'total_size_bytes' => 0,
            'notes_with_frontmatter' => 0,
            'notes_with_tags' => 0,
            'notes_with_links' => 0,
            'orphan_notes' => 0,
            'average_note_length' => 0,
            'longest_note' => null,
            'most_linked_note' => null,
            'tag_distribution' => [],
            'creation_timeline' => [],
            'health_score' => 0,
        ];

        $noteLengths = [];
        $linkCounts = [];
        $tagCounts = [];

        foreach ($files as $file) {
            if (! str_ends_with($file, '.md')) {
                continue;
            }

            $stats['markdown_files']++;

            try {
                $content = $this->vault->get($file);
                $stats['total_size_bytes'] += strlen($content);

                $frontmatter = $this->extractFrontmatter($content);
                if (! empty($frontmatter)) {
                    $stats['notes_with_frontmatter']++;
                }

                $tags = $this->extractAllTags($content, true);
                if (! empty($tags)) {
                    $stats['notes_with_tags']++;
                    foreach ($tags as $tag) {
                        $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                    }
                }

                $links = $this->extractLinks($content, false);
                $linkCount = count($links);
                $linkCounts[$file] = $linkCount;

                if ($linkCount > 0) {
                    $stats['notes_with_links']++;
                }

                $wordCount = str_word_count(strip_tags($content));
                $noteLengths[$file] = $wordCount;

            } catch (\Exception $e) {
                continue;
            }
        }

        // Calculate derived stats
        if (! empty($noteLengths)) {
            $stats['average_note_length'] = round(array_sum($noteLengths) / count($noteLengths));
            $longestFile = array_keys($noteLengths, max($noteLengths))[0];
            $stats['longest_note'] = ['file' => $longestFile, 'word_count' => $noteLengths[$longestFile]];
        }

        if (! empty($linkCounts)) {
            $mostLinkedFile = array_keys($linkCounts, max($linkCounts))[0];
            $stats['most_linked_note'] = ['file' => $mostLinkedFile, 'link_count' => $linkCounts[$mostLinkedFile]];
            $stats['orphan_notes'] = count(array_filter($linkCounts, fn ($count) => $count === 0));
        }

        // Health score calculation (simple heuristic)
        $healthFactors = [
            'frontmatter_usage' => $stats['markdown_files'] > 0 ? $stats['notes_with_frontmatter'] / $stats['markdown_files'] : 0,
            'tag_usage' => $stats['markdown_files'] > 0 ? $stats['notes_with_tags'] / $stats['markdown_files'] : 0,
            'link_usage' => $stats['markdown_files'] > 0 ? $stats['notes_with_links'] / $stats['markdown_files'] : 0,
            'orphan_ratio' => $stats['markdown_files'] > 0 ? 1 - ($stats['orphan_notes'] / $stats['markdown_files']) : 1,
        ];

        $stats['health_score'] = round(array_sum($healthFactors) / count($healthFactors) * 100);
        $stats['health_factors'] = $healthFactors;

        return new PrimitiveResource($stats);
    }

    // Helper methods
    private function findMatches(array $lines, string $pattern, bool $isRegex, bool $caseSensitive, bool $includeFrontmatter): array
    {
        $matches = [];
        $inFrontmatter = false;
        $frontmatterLines = 0;

        foreach ($lines as $lineNum => $line) {
            // Track frontmatter boundaries
            if (trim($line) === '---') {
                if ($lineNum === 0) {
                    $inFrontmatter = true;
                    $frontmatterLines = 0;

                    continue;
                } elseif ($inFrontmatter) {
                    $inFrontmatter = false;

                    continue;
                }
            }

            if ($inFrontmatter) {
                $frontmatterLines++;
                if (! $includeFrontmatter) {
                    continue;
                }
            }

            $searchLine = $caseSensitive ? $line : strtolower($line);
            $searchPattern = $caseSensitive ? $pattern : strtolower($pattern);

            $match = false;
            if ($isRegex) {
                $match = @preg_match('/'.$pattern.'/'.($caseSensitive ? '' : 'i'), $line);
            } else {
                $match = str_contains($searchLine, $searchPattern);
            }

            if ($match) {
                $matches[] = [
                    'line_number' => $lineNum + 1,
                    'line_content' => $line,
                    'in_frontmatter' => $inFrontmatter,
                ];
            }
        }

        return $matches;
    }

    private function extractFrontmatter(string $content): array
    {
        $lines = explode("\n", $content);
        if (count($lines) < 3 || trim($lines[0]) !== '---') {
            return [];
        }

        $frontmatterLines = [];
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '---') {
                break;
            }
            $frontmatterLines[] = $lines[$i];
        }

        if (empty($frontmatterLines)) {
            return [];
        }

        try {
            return \Symfony\Component\Yaml\Yaml::parse(implode("\n", $frontmatterLines)) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function matchesWhere(array $frontmatter, array $where): bool
    {
        foreach ($where as $field => $condition) {
            $value = $frontmatter[$field] ?? null;

            if (is_array($condition)) {
                // Handle operators like ['>=', 5] or ['in', ['tag1', 'tag2']]
                $operator = $condition[0] ?? '=';
                $compareValue = $condition[1] ?? null;

                if (! $this->compareValues($value, $operator, $compareValue)) {
                    return false;
                }
            } else {
                // Simple equality check
                if ($value !== $condition) {
                    return false;
                }
            }
        }

        return true;
    }

    private function compareValues($value, string $operator, $compareValue): bool
    {
        return match ($operator) {
            '=' => $value === $compareValue,
            '!=' => $value !== $compareValue,
            '>' => $value > $compareValue,
            '>=' => $value >= $compareValue,
            '<' => $value < $compareValue,
            '<=' => $value <= $compareValue,
            'in' => is_array($compareValue) && in_array($value, $compareValue),
            'contains' => is_array($value) && in_array($compareValue, $value),
            'like' => is_string($value) && str_contains(strtolower($value), strtolower($compareValue)),
            default => false,
        };
    }

    private function extractLinks(string $content, bool $includeTags): array
    {
        $links = [];

        // Wikilinks [[link]]
        preg_match_all('/\[\[([^\]]+)\]\]/', $content, $wikilinks);
        foreach ($wikilinks[1] as $link) {
            $parts = explode('|', $link);
            $links[] = [
                'type' => 'wikilink',
                'target' => trim($parts[0]),
                'display' => trim($parts[1] ?? $parts[0]),
            ];
        }

        // Markdown links [text](url)
        preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $content, $mdlinks);
        foreach ($mdlinks[1] as $i => $text) {
            $url = $mdlinks[2][$i];
            if (! str_starts_with($url, 'http')) {
                $links[] = [
                    'type' => 'markdown',
                    'target' => $url,
                    'display' => $text,
                ];
            }
        }

        // Tags if requested
        if ($includeTags) {
            preg_match_all('/#([a-zA-Z0-9_\/\-]+)/', $content, $tags);
            foreach ($tags[1] as $tag) {
                $links[] = [
                    'type' => 'tag',
                    'target' => $tag,
                    'display' => '#'.$tag,
                ];
            }
        }

        return $links;
    }

    private function findMentions(string $content, string $noteBasename): array
    {
        $mentions = [];
        $lines = explode("\n", $content);

        foreach ($lines as $lineNum => $line) {
            if (str_contains(strtolower($line), strtolower($noteBasename))) {
                $mentions[] = [
                    'line_number' => $lineNum + 1,
                    'line_content' => trim($line),
                ];
            }
        }

        return $mentions;
    }

    private function extractAllTags(string $content, bool $includeNested): array
    {
        $tags = [];

        // Frontmatter tags
        $frontmatter = $this->extractFrontmatter($content);
        if (isset($frontmatter['tags'])) {
            $fmTags = is_array($frontmatter['tags']) ? $frontmatter['tags'] : [$frontmatter['tags']];
            $tags = array_merge($tags, $fmTags);
        }

        // Inline tags
        preg_match_all('/#([a-zA-Z0-9_\/\-]+)/', $content, $matches);
        $tags = array_merge($tags, $matches[1]);

        // Generate nested tags if requested
        if ($includeNested) {
            $allTags = [];
            foreach ($tags as $tag) {
                $allTags[] = $tag;
                $parts = explode('/', $tag);
                $path = '';
                foreach ($parts as $part) {
                    $path = $path ? $path.'/'.$part : $part;
                    if ($path !== $tag) {
                        $allTags[] = $path;
                    }
                }
            }
            $tags = array_unique($allTags);
        }

        return array_unique($tags);
    }

    private function buildTagHierarchy(array $tagCounts): array
    {
        $hierarchy = [];

        foreach ($tagCounts as $tag => $count) {
            $parts = explode('/', $tag);
            $current = &$hierarchy;

            foreach ($parts as $part) {
                if (! isset($current[$part])) {
                    $current[$part] = ['_count' => 0, '_children' => []];
                }
                $current[$part]['_count'] += $count;
                $current = &$current[$part]['_children'];
            }
        }

        return $hierarchy;
    }
}
