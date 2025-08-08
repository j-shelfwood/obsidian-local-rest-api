<?php

use Illuminate\Support\Facades\Storage;

describe('File Write Operations', function () {
    beforeEach(function () {
        Storage::fake('vault');
        Storage::disk('vault')->put('existing.txt', 'Original content');
    });

    describe('write method', function () {
        test('creates new file with overwrite mode', function () {
            $response = $this->postJson('/api/files/write', [
                'path' => 'new-file.txt',
                'content' => 'New content',
                'mode' => 'overwrite',
            ]);

            $response->assertOk()
                ->assertJson([
                    'message' => 'File created successfully',
                    'path' => 'new-file.txt',
                    'mode' => 'overwrite',
                ]);

            expect(Storage::disk('vault')->get('new-file.txt'))->toBe('New content');
        });

        test('overwrites existing file with overwrite mode', function () {
            $response = $this->postJson('/api/files/write', [
                'path' => 'existing.txt',
                'content' => 'Overwritten content',
                'mode' => 'overwrite',
            ]);

            $response->assertOk()
                ->assertJson([
                    'message' => 'File updated successfully (overwrite)',
                    'path' => 'existing.txt',
                    'mode' => 'overwrite',
                ]);

            expect(Storage::disk('vault')->get('existing.txt'))->toBe('Overwritten content');
        });

        test('appends to existing file with append mode', function () {
            $response = $this->postJson('/api/files/write', [
                'path' => 'existing.txt',
                'content' => ' Appended text',
                'mode' => 'append',
            ]);

            $response->assertOk()
                ->assertJson([
                    'message' => 'File updated successfully (append)',
                    'path' => 'existing.txt',
                    'mode' => 'append',
                ]);

            expect(Storage::disk('vault')->get('existing.txt'))->toBe('Original content Appended text');
        });

        test('prepends to existing file with prepend mode', function () {
            $response = $this->postJson('/api/files/write', [
                'path' => 'existing.txt',
                'content' => 'Prepended text ',
                'mode' => 'prepend',
            ]);

            $response->assertOk()
                ->assertJson([
                    'message' => 'File updated successfully (prepend)',
                    'path' => 'existing.txt',
                    'mode' => 'prepend',
                ]);

            expect(Storage::disk('vault')->get('existing.txt'))->toBe('Prepended text Original content');
        });

        test('defaults to overwrite mode when mode not specified', function () {
            $response = $this->postJson('/api/files/write', [
                'path' => 'default-mode.txt',
                'content' => 'Default content',
            ]);

            $response->assertOk()
                ->assertJson([
                    'mode' => 'overwrite',
                ]);
        });

        test('creates file when using append mode on non-existent file', function () {
            $response = $this->postJson('/api/files/write', [
                'path' => 'new-append.txt',
                'content' => 'New content',
                'mode' => 'append',
            ]);

            $response->assertOk()
                ->assertJson([
                    'message' => 'File created successfully',
                ]);

            expect(Storage::disk('vault')->get('new-append.txt'))->toBe('New content');
        });

        test('validates required fields', function () {
            $response = $this->postJson('/api/files/write', [
                'path' => 'test.txt',
                // Missing content
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['content']);
        });

        test('validates mode field', function () {
            $response = $this->postJson('/api/files/write', [
                'path' => 'test.txt',
                'content' => 'content',
                'mode' => 'invalid-mode',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['mode']);
        });

        test('returns file size in response', function () {
            $content = 'Test content for size check';
            $response = $this->postJson('/api/files/write', [
                'path' => 'size-test.txt',
                'content' => $content,
            ]);

            $response->assertOk()
                ->assertJson([
                    'size' => strlen($content),
                ]);
        });
    });
});
