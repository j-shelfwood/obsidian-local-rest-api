<?php

use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

describe('FileController', function () {
    beforeEach(function () {
        Storage::fake('vault');
    });

    test('files index returns list of all files', function () {
        Storage::disk('vault')->put('a.txt', 'A');
        Storage::disk('vault')->put('b.md', 'B');

        getJson('/api/files')
            ->assertOk()
            ->assertExactJson([
                'a.txt',
                'b.md',
            ]);
    });

    test('files raw returns plaintext content', function () {
        Storage::disk('vault')->put('file.md', 'hello');
        $encodedPath = urlencode('file.md');

        $response = get("/api/files/{$encodedPath}");

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type');
        expect($contentType)->toBeIn(['text/plain; charset=UTF-8', 'application/octet-stream']);
        $response->assertSeeText('hello');
    });

    test('files raw returns 404 if file not found', function () {
        $encodedPath = urlencode('missing.md');
        get("/api/files/{$encodedPath}")
            ->assertNotFound()
            ->assertJson(['error' => 'File not found']);
    });

    test('file store creates new file with content', function () {
        postJson('/api/files', [
            'path' => 'f.txt',
            'content' => 'Hello World',
        ])
            ->assertCreated()
            ->assertJson(['message' => 'File created successfully', 'path' => 'f.txt']);

        expect(Storage::disk('vault')->get('f.txt'))->toBe('Hello World');
    });

    test('file update changes file content', function () {
        Storage::disk('vault')->put('up.txt', 'Old Content');
        $encoded = urlencode('up.txt');

        putJson("/api/files/{$encoded}", [
            'content' => 'New Content',
        ])
            ->assertOk()
            ->assertJson(['message' => 'File updated successfully', 'path' => 'up.txt']);

        expect(Storage::disk('vault')->get('up.txt'))->toBe('New Content');
    });

    test('file delete removes file successfully', function () {
        Storage::disk('vault')->put('del.txt', 'X');
        $encoded = urlencode('del.txt');

        deleteJson("/api/files/{$encoded}")
            ->assertOk()
            ->assertJson(['message' => 'File deleted successfully']);

        expect(Storage::disk('vault')->exists('del.txt'))->toBeFalse();
    });

    test('file delete returns 404 if file not found', function () {
        $encoded = urlencode('no.txt');

        deleteJson("/api/files/{$encoded}")
            ->assertNotFound()
            ->assertJson(['error' => 'File not found']);
    });

    test('file update returns 404 if file not found', function () {
        $encoded = urlencode('no.txt');

        putJson("/api/files/{$encoded}", ['content' => 'X'])
            ->assertNotFound()
            ->assertJson(['error' => 'File not found']);
    });

    test('file store returns conflict when directory exists', function () {
        Storage::disk('vault')->makeDirectory('dir');

        postJson('/api/files', ['path' => 'dir', 'type' => 'directory'])
            ->assertStatus(409)
            ->assertJson(['error' => 'Directory already exists']);
    });

    it('returns 409 when creating a file that already exists', function () {
        Storage::disk('vault')->put('exists.txt', 'X');

        postJson('/api/files', ['path' => 'exists.txt'])
            ->assertStatus(409)
            ->assertJson(['error' => 'File already exists']);
    });

    it('creates a directory when type=directory', function () {
        postJson('/api/files', ['path' => 'dir/subdir', 'type' => 'directory'])
            ->assertStatus(201)
            ->assertJson(['message' => 'Directory created successfully', 'path' => 'dir/subdir']);

        expect(Storage::disk('vault')->exists('dir/subdir'))->toBeTrue();
    });

    it('returns 422 if an invalid type is provided', function () {
        postJson('/api/files', ['path' => 'some', 'type' => 'invalid'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    });

    it('returns 422 when path is missing', function () {
        postJson('/api/files', ['content' => 'foo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('path');
    });

    it('returns 404 when trying to show a directory', function () {
        Storage::disk('vault')->makeDirectory('mydir');
        $encoded = urlencode('mydir');

        get("/api/files/{$encoded}")
            ->assertStatus(404)
            ->assertJson(['error' => 'File not found']);
    });

    it('deletes a directory successfully', function () {
        Storage::disk('vault')->makeDirectory('toDel');
        $encoded = urlencode('toDel');

        deleteJson("/api/files/{$encoded}")
            ->assertOk()
            ->assertJson(['message' => 'Directory deleted successfully']);

        expect(Storage::disk('vault')->exists('toDel'))->toBeFalse();
    });
});
