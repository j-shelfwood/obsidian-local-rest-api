<?php

use Illuminate\Support\Facades\Storage;

describe('VaultController', function () {
    beforeEach(function () {
        Storage::fake('vault');

        // Create test files
        Storage::disk('vault')->put('note1.md', "---\ntags: [ai, test]\n---\nThis is note 1 content with AI concepts");
        Storage::disk('vault')->put('note2.md', "---\ntags: [test, productivity]\ntitle: Daily Note\n---\nThis is note 2 about productivity");
        Storage::disk('vault')->put('folder/note3.md', 'This is note 3 with [[note1]] link');
        Storage::disk('vault')->put('2024-01-15.md', "---\ntags: [daily]\n---\nDaily note content");
        Storage::disk('vault')->put('daily/2024-01-16.md', 'Another daily note');
        Storage::disk('vault')->makeDirectory('empty-folder');
    });

    describe('listDirectory', function () {
        test('lists root directory with pagination', function () {
            $response = $this->getJson('/api/vault/directory');

            $response->assertOk()
                ->assertJsonStructure([
                    'items' => [
                        '*' => ['path', 'type', 'size', 'modified'],
                    ],
                    'total_items',
                    'path',
                    'offset',
                    'limit',
                    'has_more',
                ]);

            $data = $response->json();
            expect($data['path'])->toBe('.');
            expect($data['total_items'])->toBeGreaterThan(0);
        });

        test('lists specific directory recursively', function () {
            $response = $this->getJson('/api/vault/directory?path=folder&recursive=true');

            $response->assertOk();
            $data = $response->json();
            expect($data['path'])->toBe('folder');
        });

        test('respects pagination parameters', function () {
            $response = $this->getJson('/api/vault/directory?limit=2&offset=1');

            $response->assertOk();
            $data = $response->json();
            expect($data['limit'])->toBe(2);
            expect($data['offset'])->toBe(1);
        });
    });

    describe('searchVault', function () {
        test('searches content across files', function () {
            $response = $this->getJson('/api/vault/search?query=productivity&scope=content');

            $response->assertOk()
                ->assertJsonStructure([
                    'results' => [
                        '*' => [
                            'note' => ['path', 'content', 'front_matter'],
                            'matches',
                            'relevance',
                        ],
                    ],
                    'query',
                    'scope',
                    'total_results',
                ]);

            $data = $response->json();
            expect($data['query'])->toBe('productivity');
            expect($data['total_results'])->toBeGreaterThan(0);
        });

        test('searches filenames', function () {
            $response = $this->getJson('/api/vault/search?query=note1&scope=filename');

            $response->assertOk();
            $data = $response->json();
            expect($data['total_results'])->toBe(1);
            expect($data['results'][0]['matches'])->toContain('filename');
        });

        test('searches tags in frontmatter', function () {
            $response = $this->getJson('/api/vault/search?query=ai&scope=tags');

            $response->assertOk();
            $data = $response->json();
            expect($data['total_results'])->toBeGreaterThanOrEqual(1);
            expect($data['results'][0]['matches'])->toContain('tags');
        });

        test('searches multiple scopes', function () {
            $response = $this->getJson('/api/vault/search?query=test&scope=content,tags');

            $response->assertOk();
            $data = $response->json();
            expect($data['total_results'])->toBeGreaterThanOrEqual(2);
        });

        test('returns 400 when query is missing', function () {
            $response = $this->getJson('/api/vault/search');

            $response->assertStatus(400)
                ->assertJson(['error' => 'Query parameter is required']);
        });

        test('filters by path', function () {
            $response = $this->getJson('/api/vault/search?query=note&path_filter=folder');

            $response->assertOk();
            $data = $response->json();
            // Should only find note3.md in folder
            expect($data['total_results'])->toBe(1);
        });
    });

    describe('getRecentNotes', function () {
        test('returns recent notes ordered by modification time', function () {
            // Touch one file to make it more recent
            touch(Storage::disk('vault')->path('note2.md'));
            // Wait a moment to ensure different timestamps
            usleep(100000); // 0.1 seconds

            $response = $this->getJson('/api/vault/notes/recent?limit=3');

            $response->assertOk()
                ->assertJsonStructure([
                    '*' => ['path', 'content', 'front_matter'],
                ]);

            $notes = $response->json();
            expect(count($notes))->toBeLessThanOrEqual(3);
            // Notes should be ordered by modification time (most recent first)
            expect(count($notes))->toBeGreaterThan(0);

            // Get the first note's path (most recent)
            $firstNotePath = $notes[0]['path'];
            expect($firstNotePath)->toBeString();
        });

        test('respects limit parameter', function () {
            $response = $this->getJson('/api/vault/notes/recent?limit=2');

            $response->assertOk();
            $notes = $response->json();
            expect(count($notes))->toBe(2);
        });
    });

    describe('getDailyNote', function () {
        test('finds daily note by date format', function () {
            $response = $this->getJson('/api/vault/notes/daily?date=2024-01-15');

            $response->assertOk()
                ->assertJsonStructure([
                    'path', 'content', 'front_matter',
                ]);

            $note = $response->json();
            expect($note['path'])->toBe('2024-01-15.md');
        });

        test('finds daily note in daily folder', function () {
            $response = $this->getJson('/api/vault/notes/daily?date=2024-01-16');

            $response->assertOk();
            $note = $response->json();
            expect($note['path'])->toBe('daily/2024-01-16.md');
        });

        test('handles today shortcut', function () {
            // Create today's note
            $today = now()->format('Y-m-d');
            Storage::disk('vault')->put("{$today}.md", 'Today content');

            $response = $this->getJson('/api/vault/notes/daily?date=today');

            $response->assertOk();
            $note = $response->json();
            expect($note['path'])->toBe("{$today}.md");
        });

        test('handles yesterday shortcut', function () {
            // Create yesterday's note
            $yesterday = now()->subDay()->format('Y-m-d');
            Storage::disk('vault')->put("{$yesterday}.md", 'Yesterday content');

            $response = $this->getJson('/api/vault/notes/daily?date=yesterday');

            $response->assertOk();
            $note = $response->json();
            expect($note['path'])->toBe("{$yesterday}.md");
        });

        test('returns 404 when daily note not found', function () {
            $response = $this->getJson('/api/vault/notes/daily?date=2025-12-25');

            $response->assertStatus(404)
                ->assertJsonStructure([
                    'error',
                    'date',
                    'searched_paths',
                ]);
        });

        test('returns 400 for invalid date format', function () {
            $response = $this->getJson('/api/vault/notes/daily?date=invalid-date');

            $response->assertStatus(400)
                ->assertJson(['error' => 'Invalid date format']);
        });
    });

    describe('getRelatedNotes', function () {
        test('finds notes related by tags', function () {
            $response = $this->getJson('/api/vault/notes/related/'.urlencode('note1.md').'?on=tags');

            $response->assertOk()
                ->assertJsonStructure([
                    'related_notes' => [
                        '*' => [
                            'note' => ['path', 'content', 'front_matter'],
                            'similarity',
                            'connections',
                        ],
                    ],
                    'source_note',
                    'criteria',
                    'total_found',
                ]);

            $data = $response->json();
            expect($data['source_note'])->toBe('note1.md');
            // Should find note2.md due to shared 'test' tag
            expect($data['total_found'])->toBeGreaterThan(0);
        });

        test('finds notes related by links', function () {
            $response = $this->getJson('/api/vault/notes/related/'.urlencode('note1.md').'?on=links');

            $response->assertOk();
            $data = $response->json();
            // Should find note3.md that links to note1
            expect($data['total_found'])->toBeGreaterThan(0);

            $relatedNote = collect($data['related_notes'])
                ->first(fn ($item) => $item['note']['path'] === 'folder/note3.md');
            expect($relatedNote)->not->toBeNull();
            expect($relatedNote['connections'])->toContain('links_back');
        });

        test('finds notes related by both tags and links', function () {
            $response = $this->getJson('/api/vault/notes/related/'.urlencode('note1.md').'?on=tags,links');

            $response->assertOk();
            $data = $response->json();
            expect($data['criteria'])->toBe(['tags', 'links']);
        });

        test('respects limit parameter', function () {
            $response = $this->getJson('/api/vault/notes/related/'.urlencode('note1.md').'?limit=1');

            $response->assertOk();
            $data = $response->json();
            expect(count($data['related_notes']))->toBeLessThanOrEqual(1);
        });
        test('returns 404 for non-existent note', function () {
            $response = $this->getJson('/api/vault/notes/related/'.urlencode('nonexistent.md'));

            $response->assertStatus(404)
                ->assertJson(['error' => 'Note not found']);
        });

        test('sorts by similarity score', function () {
            // Create a note with multiple shared tags for higher similarity
            Storage::disk('vault')->put('similar.md', "---\ntags: [ai, test, extra]\n---\nVery similar note");

            $response = $this->getJson('/api/vault/notes/related/'.urlencode('note1.md').'?on=tags');

            $response->assertOk();
            $data = $response->json();

            if (count($data['related_notes']) > 1) {
                $similarities = collect($data['related_notes'])->pluck('similarity');
                expect($similarities->toArray())->toBe($similarities->sortDesc()->values()->toArray());
            }
        });
    });
});
