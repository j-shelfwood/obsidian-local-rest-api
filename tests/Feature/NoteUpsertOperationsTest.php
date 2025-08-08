<?php

use Illuminate\Support\Facades\Storage;

describe('Note Upsert Operations', function () {
    beforeEach(function () {
        Storage::fake('vault');
        Storage::disk('vault')->put('existing-note.md', "---\ntags: [old]\ntitle: Old Title\n---\nOriginal content");
    });

    describe('upsert method', function () {
        test('creates new note when it does not exist', function () {
            $response = $this->postJson('/api/notes/upsert', [
                'path' => 'new-note.md',
                'content' => 'This is new content',
                'front_matter' => [
                    'tags' => ['new', 'test'],
                    'title' => 'New Note',
                ],
            ]);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'path', 'content', 'front_matter',
                ]);

            $note = $response->json();
            expect($note['path'])->toBe('new-note.md');
            expect($note['content'])->toBe('This is new content');
            expect($note['front_matter']['tags'])->toBe(['new', 'test']);
            expect($note['front_matter']['title'])->toBe('New Note');

            // Verify file was created
            expect(Storage::disk('vault')->exists('new-note.md'))->toBeTrue();
        });

        test('updates existing note when it exists', function () {
            $response = $this->postJson('/api/notes/upsert', [
                'path' => 'existing-note.md',
                'content' => 'Updated content',
                'front_matter' => [
                    'tags' => ['updated', 'test'],
                    'title' => 'Updated Title',
                ],
            ]);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'path', 'content', 'front_matter',
                ]);

            $note = $response->json();
            expect($note['path'])->toBe('existing-note.md');
            expect($note['content'])->toBe('Updated content');
            expect($note['front_matter']['tags'])->toBe(['updated', 'test']);
            expect($note['front_matter']['title'])->toBe('Updated Title');

            // Verify file was updated
            $fileContent = Storage::disk('vault')->get('existing-note.md');
            expect($fileContent)->toContain('Updated content');
            expect($fileContent)->toContain('updated');
        });

        test('automatically adds .md extension if missing', function () {
            $response = $this->postJson('/api/notes/upsert', [
                'path' => 'without-extension',
                'content' => 'Content for note without extension',
            ]);

            $response->assertStatus(201);
            $note = $response->json();
            expect($note['path'])->toBe('without-extension.md');
            expect(Storage::disk('vault')->exists('without-extension.md'))->toBeTrue();
        });

        test('creates note without front matter when not provided', function () {
            $response = $this->postJson('/api/notes/upsert', [
                'path' => 'no-frontmatter.md',
                'content' => 'Just content, no frontmatter',
            ]);

            $response->assertStatus(201);
            $note = $response->json();
            expect($note['front_matter'])->toBe([]);

            // Verify file content doesn't have frontmatter delimiters
            $fileContent = Storage::disk('vault')->get('no-frontmatter.md');
            expect($fileContent)->not->toContain('---');
            expect($fileContent)->toBe('Just content, no frontmatter');
        });

        test('creates note without content when not provided', function () {
            $response = $this->postJson('/api/notes/upsert', [
                'path' => 'no-content.md',
                'front_matter' => [
                    'tags' => ['empty'],
                    'title' => 'Empty Note',
                ],
            ]);

            $response->assertStatus(201);
            $note = $response->json();
            expect($note['content'])->toBe('');

            // Verify file has frontmatter but no content
            $fileContent = Storage::disk('vault')->get('no-content.md');
            expect($fileContent)->toContain('tags:');
            expect($fileContent)->toContain('- empty');
            expect($fileContent)->toEndWith("---\n");
        });

        test('handles empty front matter and content', function () {
            $response = $this->postJson('/api/notes/upsert', [
                'path' => 'completely-empty.md',
            ]);

            $response->assertStatus(201);
            $note = $response->json();
            expect($note['content'])->toBe('');
            expect($note['front_matter'])->toBe([]);

            // Verify file is completely empty
            $fileContent = Storage::disk('vault')->get('completely-empty.md');
            expect($fileContent)->toBe('');
        });

        test('validates required path field', function () {
            $response = $this->postJson('/api/notes/upsert', [
                'content' => 'Content without path',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['path']);
        });

        test('handles nested directory paths', function () {
            $response = $this->postJson('/api/notes/upsert', [
                'path' => 'folder/subfolder/nested-note.md',
                'content' => 'Note in nested directory',
            ]);

            $response->assertStatus(201);
            $note = $response->json();
            expect($note['path'])->toBe('folder/subfolder/nested-note.md');
            expect(Storage::disk('vault')->exists('folder/subfolder/nested-note.md'))->toBeTrue();
        });

        test('preserves complex front matter structure', function () {
            $complexFrontMatter = [
                'tags' => ['complex', 'structure'],
                'title' => 'Complex Note',
                'metadata' => [
                    'author' => 'Test Author',
                    'date' => '2024-01-01',
                    'nested' => [
                        'level1' => 'value1',
                        'level2' => ['array', 'values'],
                    ],
                ],
            ];

            $response = $this->postJson('/api/notes/upsert', [
                'path' => 'complex-note.md',
                'content' => 'Content with complex frontmatter',
                'front_matter' => $complexFrontMatter,
            ]);

            $response->assertStatus(201);
            $note = $response->json();
            expect($note['front_matter'])->toBe($complexFrontMatter);

            // Verify the YAML structure is preserved in the file
            $fileContent = Storage::disk('vault')->get('complex-note.md');
            expect($fileContent)->toContain('author: \'Test Author\'');
            expect($fileContent)->toContain('level1: value1');
        });

        test('overwrites existing note completely on upsert', function () {
            // First, verify the existing note has the expected content
            $existingContent = Storage::disk('vault')->get('existing-note.md');
            expect($existingContent)->toContain('Original content');
            expect($existingContent)->toContain('Old Title');

            // Now upsert with completely different content
            $response = $this->postJson('/api/notes/upsert', [
                'path' => 'existing-note.md',
                'content' => 'Completely new content',
                'front_matter' => [
                    'tags' => ['replaced'],
                    'author' => 'New Author',
                ],
            ]);

            $response->assertStatus(200);

            // Verify the old content is completely replaced
            $newContent = Storage::disk('vault')->get('existing-note.md');
            expect($newContent)->not->toContain('Original content');
            expect($newContent)->not->toContain('Old Title');
            expect($newContent)->not->toContain('old');
            expect($newContent)->toContain('Completely new content');
            expect($newContent)->toContain('replaced');
            expect($newContent)->toContain('New Author');
        });
    });
});
