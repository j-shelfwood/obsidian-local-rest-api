<?php

use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\getJson;

describe('MetadataController', function () {
    beforeEach(function () {
        Storage::fake('vault');
    });

    test('metadata keys returns unique keys', function () {
        Storage::disk('vault')->put('k1.md', "---\na: 1\nb: 2\n---\n");
        Storage::disk('vault')->put('k2.md', "---\na: 3\nc: 4\n---\n");

        getJson('/api/metadata/keys')
            ->assertOk()
            ->assertExactJson(['a', 'b', 'c']);
    });

    test('metadata values returns unique values for a key', function () {
        Storage::disk('vault')->put('v1.md', "---\na: 1\n---\n");
        Storage::disk('vault')->put('v2.md', "---\na: 2\n---\n");

        getJson('/api/metadata/values/a')
            ->assertOk()
            ->assertExactJson([1, 2]);
    });
});
