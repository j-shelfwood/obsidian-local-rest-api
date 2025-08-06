<?php

use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\postJson;

describe('Enhanced AI-Native Endpoints', function () {
    beforeEach(function () {
        Storage::fake('vault');
    });

    describe('FileController write method', function () {
        it('creates new file in overwrite mode', function () {
            postJson('/api/files/write', [
                'path' => 'new-file.txt',
                'content' => 'Hello World',
                'mode' => 'overwrite'
            ])
                ->assertOk()
                ->assertJson([
                    'message' => 'File created successfully',
                    'path' => 'new-file.txt',
                    'mode' => 'overwrite'
                ]);

            expect(Storage::disk('vault')->get('new-file.txt'))->toBe('Hello World');
        });

        it('overwrites existing file', function () {
            Storage::disk('vault')->put('existing.txt', 'Old content');

            postJson('/api/files/write', [
                'path' => 'existing.txt',
                'content' => 'New content',
                'mode' => 'overwrite'
            ])
                ->assertOk()
                ->assertJsonFragment(['message' => 'File updated successfully (overwrite)']);

            expect(Storage::disk('vault')->get('existing.txt'))->toBe('New content');
        });

        it('appends to existing file', function () {
            Storage::disk('vault')->put('append-test.txt', 'Original content');

            postJson('/api/files/write', [
                'path' => 'append-test.txt',
                'content' => '\nAppended content',
                'mode' => 'append'
            ])
                ->assertOk()
                ->assertJsonFragment(['mode' => 'append']);

            expect(Storage::disk('vault')->get('append-test.txt'))
                ->toBe("Original content\nAppended content");
        });

        it('prepends to existing file', function () {
            Storage::disk('vault')->put('prepend-test.txt', 'Original content');

            postJson('/api/files/write', [
                'path' => 'prepend-test.txt',
                'content' => 'Prepended content\n',
                'mode' => 'prepend'
            ])
                ->assertOk()
                ->assertJsonFragment(['mode' => 'prepend']);

            expect(Storage::disk('vault')->get('prepend-test.txt'))
                ->toBe("Prepended content\nOriginal content");
        });

        it('defaults to overwrite mode when mode not specified', function () {
            postJson('/api/files/write', [
                'path' => 'default-mode.txt',
                'content' => 'Content'
            ])
                ->assertOk()
                ->assertJsonPath('mode', 'overwrite');
        });

        it('validates required fields', function () {
            postJson('/api/files/write', [])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['path', 'content']);
        });

        it('validates mode field', function () {
            postJson('/api/files/write', [
                'path' => 'test.txt',
                'content' => 'content',
                'mode' => 'invalid'
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['mode']);
        });

        it('returns file size in response', function () {
            postJson('/api/files/write', [
                'path' => 'size-test.txt',
                'content' => 'Hello'
            ])
                ->assertOk()
                ->assertJsonPath('size', 5);
        });
    });

    describe('NoteController upsert method', function () {
        it('creates new note when not exists', function () {
            postJson('/api/notes/upsert', [
                'path' => 'new-note',
                'content' => 'This is a new note',
                'front_matter' => ['title' => 'New Note', 'tags' => ['test']]
            ])
                ->assertCreated()
                ->assertJsonFragment([
                    'path' => 'new-note.md',
                    'content' => 'This is a new note',
                    'front_matter' => [
                        'title' => 'New Note',
                        'tags' => ['test']
                    ]
                ]);

            expect(Storage::disk('vault')->exists('new-note.md'))->toBeTrue();
        });

        it('updates existing note', function () {
            Storage::disk('vault')->put('existing-note.md', "---\ntitle: Old Title\n---\nOld content");

            postJson('/api/notes/upsert', [
                'path' => 'existing-note.md',
                'content' => 'Updated content',
                'front_matter' => ['title' => 'Updated Title']
            ])
                ->assertOk() // 200 for update
                ->assertJsonFragment([
                    'path' => 'existing-note.md',
                    'content' => 'Updated content',
                    'front_matter' => ['title' => 'Updated Title']
                ]);

            $content = Storage::disk('vault')->get('existing-note.md');
            expect($content)->toContain('Updated Title')->toContain('Updated content');
        });

        it('automatically adds .md extension', function () {
            postJson('/api/notes/upsert', [
                'path' => 'without-extension',
                'content' => 'Content'
            ])
                ->assertCreated()
                ->assertJsonPath('path', 'without-extension.md');
        });

        it('handles note without front matter', function () {
            postJson('/api/notes/upsert', [
                'path' => 'simple-note',
                'content' => 'Just content, no front matter'
            ])
                ->assertCreated()
                ->assertJsonFragment([
                    'path' => 'simple-note.md',
                    'content' => 'Just content, no front matter',
                    'front_matter' => []
                ]);

            $content = Storage::disk('vault')->get('simple-note.md');
            expect($content)->toBe('Just content, no front matter');
        });

        it('handles note with only front matter', function () {
            postJson('/api/notes/upsert', [
                'path' => 'metadata-only',
                'front_matter' => ['status' => 'draft', 'priority' => 'high']
            ])
                ->assertCreated()
                ->assertJsonFragment([
                    'path' => 'metadata-only.md',
                    'content' => '',
                    'front_matter' => ['status' => 'draft', 'priority' => 'high']
                ]);
        });

        it('validates required path', function () {
            postJson('/api/notes/upsert', [
                'content' => 'Content without path'
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['path']);
        });

        it('validates front_matter as array', function () {
            postJson('/api/notes/upsert', [
                'path' => 'test-note',
                'front_matter' => 'not an array'
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['front_matter']);
        });

        it('handles complex front matter structures', function () {
            $complexFrontMatter = [
                'title' => 'Complex Note',
                'tags' => ['work', 'project', 'important'],
                'metadata' => [
                    'author' => 'AI Agent',
                    'created' => '2024-01-01',
                    'priority' => 5
                ]
            ];

            postJson('/api/notes/upsert', [
                'path' => 'complex-note',
                'content' => 'Note with complex metadata',
                'front_matter' => $complexFrontMatter
            ])
                ->assertCreated()
                ->assertJsonFragment(['front_matter' => $complexFrontMatter]);
        });

        it('preserves existing .md extension', function () {
            postJson('/api/notes/upsert', [
                'path' => 'note.md',
                'content' => 'Content'
            ])
                ->assertCreated()
                ->assertJsonPath('path', 'note.md');
        });
    });

    describe('Integration tests', function () {
        it('can use write and upsert together in workflow', function () {
            // First, create a log file with write
            postJson('/api/files/write', [
                'path' => 'daily-log.md',
                'content' => "# Daily Log\n\n## Tasks\n",
                'mode' => 'overwrite'
            ])->assertOk();

            // Then append to it
            postJson('/api/files/write', [
                'path' => 'daily-log.md',
                'content' => "- Completed task 1\n",
                'mode' => 'append'
            ])->assertOk();

            // Then create/update a note with upsert
            postJson('/api/notes/upsert', [
                'path' => 'task-note',
                'content' => 'Details about task 1',
                'front_matter' => ['related_log' => 'daily-log.md']
            ])->assertCreated();

            // Verify both files exist and have correct content
            $logContent = Storage::disk('vault')->get('daily-log.md');
            expect($logContent)->toContain('# Daily Log')->toContain('- Completed task 1');

            $noteContent = Storage::disk('vault')->get('task-note.md');
            expect($noteContent)->toContain('related_log: daily-log.md')->toContain('Details about task 1');
        });
    });
});