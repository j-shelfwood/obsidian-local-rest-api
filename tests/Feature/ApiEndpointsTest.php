<?php

use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Storage::fake('vault');
});

/* Files Endpoints */
test('files tree returns empty array when no files', function () {
    getJson('/api/files/tree')
        ->assertOk()
        ->assertExactJson([]);
});

test('files tree returns file node for single file', function () {
    Storage::disk('vault')->put('foo.txt', 'content');

    getJson('/api/files/tree')
        ->assertOk()
        ->assertExactJson([
            ['name' => 'foo.txt', 'type' => 'file', 'children' => []],
        ]);
});

test('files index returns list of all files', function () {
    Storage::disk('vault')->put('a.txt', 'A');
    Storage::disk('vault')->put('b.md', 'B');

    getJson('/api/files')
        ->assertOk()
        ->assertExactJson(['a.txt', 'b.md']);
});

test('files raw returns plaintext content', function () {
    Storage::disk('vault')->put('file.md', 'hello');

    get('/api/files/raw?path=file.md')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSeeText('hello');
});

test('files raw returns 404 if file not found', function () {
    get('/api/files/raw?path=missing.md')
        ->assertNotFound()
        ->assertJson(['error' => 'File not found']);
});

/* Notes Endpoints */
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

    getJson('/api/notes/n.md')
        ->assertOk()
        ->assertJson([
            'path' => 'n.md',
            'front_matter' => ['a' => 1],
            'content' => 'X',
        ]);
});

test('notes show returns 404 for missing note', function () {
    getJson('/api/notes/notfound.md')
        ->assertNotFound()
        ->assertJson(['error' => 'Note not found']);
});

test('notes search filters by front matter field and value', function () {
    Storage::disk('vault')->put('a.md', "---\ntag: foo\n---\nA");
    Storage::disk('vault')->put('b.md', "---\ntag: bar\n---\nB");

    getJson('/api/notes/search?field=tag&value=foo')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['path' => 'a.md', 'front_matter' => ['tag' => 'foo']]);
});

test('notes search returns 400 if missing query params', function () {
    getJson('/api/notes/search')
        ->assertStatus(400)
        ->assertJson(['error' => 'field and value query parameters are required']);
});

test('notes store creates new note', function () {
    postJson('/api/notes', [
        'path' => 'newnote',
        'front_matter' => ['x' => 'y'],
        'content' => 'Hello',
    ])
        ->assertCreated()
        ->assertJson(['message' => 'Note created', 'path' => 'newnote.md']);

    $raw = Storage::disk('vault')->get('newnote.md');
    expect($raw)->toContain("x: 'y'")->toContain('Hello');
});

test('notes update replaces existing note', function () {
    Storage::disk('vault')->put('u.md', "---\na: old\n---\nOLD");

    putJson('/api/notes/u.md', [
        'front_matter' => ['a' => 'new'],
        'content' => 'NEW',
    ])
        ->assertOk()
        ->assertJson(['message' => 'Note replaced', 'path' => 'u.md']);

    $raw = Storage::disk('vault')->get('u.md');
    expect($raw)->toContain('a: new')->toContain('NEW');
});

test('notes patch updates specific parts', function () {
    Storage::disk('vault')->put('p.md', "---\nb: 1\n---\nOLD");

    patchJson('/api/notes/p.md', [
        'front_matter' => ['b' => 2],
        'content' => 'UPDATED',
    ])
        ->assertOk()
        ->assertJson(['message' => 'Note updated', 'path' => 'p.md']);

    $raw = Storage::disk('vault')->get('p.md');
    expect($raw)->toContain('b: 2')->toContain('UPDATED');
});

test('notes destroy deletes the note', function () {
    Storage::disk('vault')->put('d.md', 'test');

    deleteJson('/api/notes/d.md')
        ->assertOk()
        ->assertJson(['message' => 'Note deleted', 'path' => 'd.md']);

    expect(Storage::disk('vault')->exists('d.md'))->toBeFalse();
});

/* Bulk operations */
test('notes bulk delete removes multiple notes', function () {
    Storage::disk('vault')->put('b1.md', '1');
    Storage::disk('vault')->put('b2.md', '2');

    postJson('/api/notes/bulk-delete', ['paths' => ['b1.md', 'missing.md']])
        ->assertOk()
        ->assertJson(['deleted' => ['b1.md'], 'notFound' => ['missing.md']]);
});

test('notes bulk update applies multiple updates', function () {
    Storage::disk('vault')->put('up1.md', "---\nx: a\n---\nA");
    Storage::disk('vault')->put('up2.md', "---\ny: b\n---\nB");

    postJson('/api/notes/bulk-update', ['items' => [
        ['path' => 'up1.md', 'front_matter' => ['x' => 'z']],
        ['path' => 'up2.md', 'content' => 'NEWB'],
    ]])
        ->assertOk()
        ->assertJsonCount(2, 'results')
        ->assertJsonFragment(['path' => 'up1.md', 'status' => 'updated'])
        ->assertJsonFragment(['path' => 'up2.md', 'status' => 'updated']);

    $raw1 = Storage::disk('vault')->get('up1.md');
    $raw2 = Storage::disk('vault')->get('up2.md');
    expect($raw1)->toContain('x: z');
    expect($raw2)->toContain('NEWB');
});

/* Front Matter Endpoints */
test('front-matter keys returns unique keys', function () {
    Storage::disk('vault')->put('k1.md', "---\na: 1\nb: 2\n---\n");
    Storage::disk('vault')->put('k2.md', "---\na: 3\nc: 4\n---\n");

    getJson('/api/front-matter/keys')
        ->assertOk()
        ->assertExactJson(['a', 'b', 'c']);
});

test('front-matter values returns unique values for a key', function () {
    Storage::disk('vault')->put('v1.md', "---\na: 1\n---\n");
    Storage::disk('vault')->put('v2.md', "---\na: 2\n---\n");

    getJson('/api/front-matter/values/a')
        ->assertOk()
        ->assertExactJson([1, 2]);
});
