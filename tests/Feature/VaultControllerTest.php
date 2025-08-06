<?php

use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\getJson;

describe('VaultController (AI-Native Endpoints)', function () {
    beforeEach(function () {
        Storage::fake('vault');
    });

    describe('listDirectory', function () {
        it('lists directory contents with pagination', function () {
            Storage::disk('vault')->put('file1.md', 'content1');
            Storage::disk('vault')->put('file2.txt', 'content2');
            Storage::disk('vault')->makeDirectory('subdir');

            getJson('/api/vault/directory')
                ->assertOk()
                ->assertJsonStructure([
                    'items' => [
                        '*' => ['path', 'type', 'size', 'modified']
                    ],
                    'total_items',
                    'path',
                    'offset',
                    'limit',
                    'has_more'
                ])
                ->assertJsonFragment(['path' => 'file1.md', 'type' => 'file'])
                ->assertJsonFragment(['path' => 'file2.txt', 'type' => 'file']);
        });

        it('applies pagination correctly', function () {
            Storage::disk('vault')->put('file1.md', 'content');
            Storage::disk('vault')->put('file2.md', 'content');
            Storage::disk('vault')->put('file3.md', 'content');

            getJson('/api/vault/directory?limit=2&offset=1')
                ->assertOk()
                ->assertJsonPath('limit', 2)
                ->assertJsonPath('offset', 1)
                ->assertJsonPath('total_items', 3);
        });

        it('handles recursive directory listing', function () {
            Storage::disk('vault')->makeDirectory('deep/nested');
            Storage::disk('vault')->put('deep/nested/note.md', 'content');

            getJson('/api/vault/directory?recursive=true')
                ->assertOk()
                ->assertJsonFragment(['path' => 'deep/nested/note.md']);
        });

        it('returns 404 for non-existent directory', function () {
            getJson('/api/vault/directory?path=nonexistent')
                ->assertNotFound()
                ->assertJson(['error' => 'Directory not found or access denied']);
        });
    });

    describe('searchVault', function () {
        beforeEach(function () {
            Storage::disk('vault')->put('note1.md', "---\ntags: [work, project]\n---\nThis is about the project");
            Storage::disk('vault')->put('note2.md', "---\ntags: [personal]\n---\nPersonal thoughts here");
            Storage::disk('vault')->put('work-meeting.md', "Meeting notes");
            Storage::disk('vault')->put('readme.txt', 'Not a markdown file');
        });

        it('searches content successfully', function () {
            getJson('/api/vault/search?query=project&scope[]=content')
                ->assertOk()
                ->assertJsonStructure([
                    'results' => [
                        '*' => ['note', 'matches', 'relevance']
                    ],
                    'query',
                    'scope',
                    'total_results'
                ])
                ->assertJsonPath('query', 'project')
                ->assertJsonPath('results.0.matches', ['content']);
        });

        it('searches filenames successfully', function () {
            getJson('/api/vault/search?query=work&scope[]=filename')
                ->assertOk()
                ->assertJsonFragment(['matches' => ['filename']]);
        });

        it('searches tags successfully', function () {
            getJson('/api/vault/search?query=work&scope[]=tags')
                ->assertOk()
                ->assertJsonFragment(['matches' => ['tags']]);
        });

        it('searches multiple scopes', function () {
            getJson('/api/vault/search?query=work&scope[]=filename&scope[]=tags')
                ->assertOk()
                ->assertJsonCount(2, 'results');
        });

        it('applies path filter', function () {
            Storage::disk('vault')->makeDirectory('archive');
            Storage::disk('vault')->put('archive/old-work.md', 'old work content');

            getJson('/api/vault/search?query=work&path_filter=archive')
                ->assertOk()
                ->assertJsonFragment(['note' => ['path' => 'archive/old-work.md']]);
        });

        it('returns error when query is missing', function () {
            getJson('/api/vault/search')
                ->assertStatus(400)
                ->assertJson(['error' => 'Query parameter is required']);
        });

        it('sorts results by relevance', function () {
            Storage::disk('vault')->put('double-match.md', "---\ntags: [work]\n---\nThis has work in content too");

            getJson('/api/vault/search?query=work&scope[]=filename&scope[]=tags&scope[]=content')
                ->assertOk()
                ->assertJsonPath('results.0.relevance', 2); // filename + tags
        });
    });

    describe('getRecentNotes', function () {
        it('returns recent notes by modification time', function () {
            Storage::disk('vault')->put('old.md', 'old content');
            sleep(1);
            Storage::disk('vault')->put('new.md', 'new content');

            getJson('/api/vault/notes/recent?limit=5')
                ->assertOk()
                ->assertJsonStructure([
                    '*' => ['path', 'front_matter', 'content', 'size', 'created_at', 'updated_at']
                ])
                ->assertJsonPath('0.path', 'new.md'); // Most recent first
        });

        it('respects limit parameter', function () {
            Storage::disk('vault')->put('note1.md', 'content');
            Storage::disk('vault')->put('note2.md', 'content');
            Storage::disk('vault')->put('note3.md', 'content');

            getJson('/api/vault/notes/recent?limit=2')
                ->assertOk()
                ->assertJsonCount(2);
        });

        it('filters only markdown files', function () {
            Storage::disk('vault')->put('note.md', 'markdown content');
            Storage::disk('vault')->put('file.txt', 'text content');

            getJson('/api/vault/notes/recent')
                ->assertOk()
                ->assertJsonCount(1)
                ->assertJsonFragment(['path' => 'note.md']);
        });
    });

    describe('getDailyNote', function () {
        it('finds daily note with standard format', function () {
            $today = now()->format('Y-m-d');
            Storage::disk('vault')->put("{$today}.md", "# Today's note");

            getJson('/api/vault/notes/daily?date=today')
                ->assertOk()
                ->assertJsonPath('path', "{$today}.md");
        });

        it('finds daily note in Daily Notes folder', function () {
            $today = now()->format('Y-m-d');
            Storage::disk('vault')->makeDirectory('Daily Notes');
            Storage::disk('vault')->put("Daily Notes/{$today}.md", "# Daily note");

            getJson('/api/vault/notes/daily?date=today')
                ->assertOk()
                ->assertJsonPath('path', "Daily Notes/{$today}.md");
        });

        it('handles yesterday and tomorrow', function () {
            $yesterday = now()->subDay()->format('Y-m-d');
            Storage::disk('vault')->put("{$yesterday}.md", "# Yesterday");

            getJson('/api/vault/notes/daily?date=yesterday')
                ->assertOk()
                ->assertJsonPath('path', "{$yesterday}.md");
        });

        it('handles specific date format', function () {
            Storage::disk('vault')->put("2024-12-25.md", "# Christmas");

            getJson('/api/vault/notes/daily?date=2024-12-25')
                ->assertOk()
                ->assertJsonPath('path', "2024-12-25.md");
        });

        it('returns 404 with searched paths when not found', function () {
            getJson('/api/vault/notes/daily?date=today')
                ->assertNotFound()
                ->assertJsonStructure([
                    'error',
                    'date',
                    'searched_paths'
                ]);
        });

        it('returns 400 for invalid date format', function () {
            getJson('/api/vault/notes/daily?date=invalid-date')
                ->assertStatus(400)
                ->assertJson(['error' => 'Invalid date format']);
        });
    });

    describe('getRelatedNotes', function () {
        beforeEach(function () {
            Storage::disk('vault')->put('source.md', "---\ntags: [work, project]\n---\nContent with [[linked-note]] reference");
            Storage::disk('vault')->put('linked-note.md', "---\ntags: [work]\n---\nThis references [[source]]");
            Storage::disk('vault')->put('same-tags.md', "---\ntags: [work, personal]\n---\nShares work tag");
            Storage::disk('vault')->put('unrelated.md', "---\ntags: [personal]\n---\nCompletely different");
        });

        it('finds related notes based on tags and links', function () {
            $encodedPath = urlencode('source.md');

            getJson("/api/vault/notes/related/{$encodedPath}")
                ->assertOk()
                ->assertJsonStructure([
                    'related_notes' => [
                        '*' => ['note', 'similarity', 'connections']
                    ],
                    'source_note',
                    'criteria',
                    'total_found'
                ])
                ->assertJsonPath('source_note', 'source.md');
        });

        it('calculates similarity scores correctly', function () {
            $encodedPath = urlencode('source.md');

            $response = getJson("/api/vault/notes/related/{$encodedPath}")
                ->assertOk();

            $data = $response->json();
            $notes = collect($data['related_notes']);

            // linked-note.md should have highest similarity (shared tags + bidirectional links)
            $linkedNote = $notes->firstWhere('note.path', 'linked-note.md');
            expect($linkedNote['similarity'])->toBeGreaterThan(2);

            // same-tags.md should have lower similarity (only shared tags)
            $sameTagsNote = $notes->firstWhere('note.path', 'same-tags.md');
            expect($sameTagsNote['similarity'])->toBeLessThan($linkedNote['similarity']);
        });

        it('filters by relationship criteria', function () {
            $encodedPath = urlencode('source.md');

            getJson("/api/vault/notes/related/{$encodedPath}?on[]=tags")
                ->assertOk()
                ->assertJsonPath('criteria', ['tags']);
        });

        it('respects limit parameter', function () {
            $encodedPath = urlencode('source.md');

            getJson("/api/vault/notes/related/{$encodedPath}?limit=1")
                ->assertOk()
                ->assertJsonCount(1, 'related_notes');
        });

        it('returns 404 for non-existent note', function () {
            $encodedPath = urlencode('nonexistent.md');

            getJson("/api/vault/notes/related/{$encodedPath}")
                ->assertNotFound()
                ->assertJson(['error' => 'Note not found']);
        });

        it('handles notes with no front matter', function () {
            Storage::disk('vault')->put('no-front.md', 'Simple content with [[source]] link');
            $encodedPath = urlencode('source.md');

            getJson("/api/vault/notes/related/{$encodedPath}")
                ->assertOk();
        });
    });
});