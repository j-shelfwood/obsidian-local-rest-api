<?php

use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\postJson;
use function Pest\Laravel\get;
use function Pest\Laravel\deleteJson;

beforeEach(fn() => Storage::fake('vault'));

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
        ->assertJson(['message' => 'File deleted successfully']);
});
