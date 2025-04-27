<?php

use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

describe('NoteController', function () {
    beforeEach(function () {
        Storage::fake('vault');
    });

    test('notes index lists all markdown notes with front matter and content', function () {
        Storage::disk('vault')->put('note1.md', "---\ntitle: Test\n---\nBody");
        Storage::disk('vault')->put('ignore.txt', 'x');

        getJson('/api/notes')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'path' => 'note1.md',
                'front_matter' => ['title' => 'Test'],
                'content' => 'Body',
            ]);
    });

    test('notes show returns note content and front matter', function () {
        Storage::disk('vault')->put('n.md', "---\na: 1\n---\nX");
        $encodedPath = urlencode('n.md');

        getJson("/api/notes/{$encodedPath}")
            ->assertOk()
            ->assertJson([
                'path' => 'n.md',
                'front_matter' => ['a' => 1],
                'content' => 'X',
            ]);
    });

    test('notes show returns 404 for missing note', function () {
        $encodedPath = urlencode('notfound.md');
        getJson("/api/notes/{$encodedPath}")
            ->assertNotFound()
            ->assertJson(['error' => 'Note not found']);
    });

    test('notes store creates new note', function () {
        postJson('/api/notes', [
            'path' => 'newnote',
            'front_matter' => ['x' => 'y'],
            'content' => 'Hello',
        ])
            ->assertCreated()
            ->assertJson([
                'path' => 'newnote.md',
                'front_matter' => ['x' => 'y'],
                'content' => 'Hello',
            ]);

        $raw = Storage::disk('vault')->get('newnote.md');
        expect($raw)->toContain("x: 'y'")->toContain('Hello');
    });

    test('notes update replaces existing note', function () {
        Storage::disk('vault')->put('u.md', "---\na: old\n---\nOLD");
        $encodedPath = urlencode('u.md');

        putJson("/api/notes/{$encodedPath}", [
            'front_matter' => ['a' => 'new'],
            'content' => 'NEW',
        ])
            ->assertOk()
            ->assertJson([
                'path' => 'u.md',
                'front_matter' => ['a' => 'new'],
                'content' => 'NEW',
            ]);

        $raw = Storage::disk('vault')->get('u.md');
        expect($raw)->toContain('a: new')->toContain('NEW');
    });

    test('notes patch updates specific parts', function () {
        Storage::disk('vault')->put('p.md', "---\nb: 1\n---\nOLD");
        $encodedPath = urlencode('p.md');

        patchJson("/api/notes/{$encodedPath}", [
            'front_matter' => ['b' => 2],
            'content' => 'UPDATED',
        ])
            ->assertOk()
            ->assertJson([
                'path' => 'p.md',
                'front_matter' => ['b' => 2],
                'content' => 'UPDATED',
            ]);

        $raw = Storage::disk('vault')->get('p.md');
        expect($raw)->toContain('b: 2')->toContain('UPDATED');
    });

    test('notes destroy deletes the note', function () {
        Storage::disk('vault')->put('d.md', 'test');
        $encodedPath = urlencode('d.md');

        deleteJson("/api/notes/{$encodedPath}")
            ->assertNoContent();

        expect(Storage::disk('vault')->exists('d.md'))->toBeFalse();
    });

    test('notes bulk delete removes multiple notes', function () {
        Storage::disk('vault')->put('b1.md', '1');
        Storage::disk('vault')->put('b2.md', '2');
        $path1 = 'b1.md';
        $missingPath = 'missing.md';

        deleteJson('/api/bulk/notes/delete', ['paths' => [$path1, $missingPath]])
            ->assertOk()
            ->assertJson(['deleted' => ['b1.md'], 'notFound' => ['missing.md']]);
    });

    test('notes bulk update applies multiple updates', function () {
        Storage::disk('vault')->put('up1.md', "---\nx: a\n---\nA");
        Storage::disk('vault')->put('up2.md', "---\ny: b\n---\nB");
        $path1 = 'up1.md';
        $path2 = 'up2.md';

        patchJson('/api/bulk/notes/update', ['items' => [
            ['path' => $path1, 'front_matter' => ['x' => 'z']],
            ['path' => $path2, 'content' => 'NEWB'],
        ]])
            ->assertOk()
            ->assertJsonCount(2, 'results')
            ->assertJsonFragment(['path' => 'up1.md', 'status' => 'updated', 'note' => ['path' => 'up1.md', 'front_matter' => ['x' => 'z'], 'content' => 'A']])
            ->assertJsonFragment(['path' => 'up2.md', 'status' => 'updated', 'note' => ['path' => 'up2.md', 'front_matter' => ['y' => 'b'], 'content' => 'NEWB']]);

        $raw1 = Storage::disk('vault')->get('up1.md');
        $raw2 = Storage::disk('vault')->get('up2.md');
        expect($raw1)->toContain('x: z');
        expect($raw2)->toContain('NEWB');
    });
});
