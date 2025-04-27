<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

class MetadataController extends Controller
{
    /**
     * Parse the YAML front matter from raw note content.
     */
    protected function parseFront(string $raw): array
    {
        if (str($raw)->startsWith('---')) {
            $parts = explode('---', $raw, 3);

            return Yaml::parse(trim($parts[1])) ?: [];
        }

        return [];
    }

    /**
     * Return all unique front matter keys across markdown notes.
     */
    public function keys()
    {
        $unique = collect(Storage::disk('vault')->allFiles())
            ->filter(fn ($path) => str($path)->endsWith('.md'))
            ->map(fn ($path) => $this->parseFront(Storage::disk('vault')->get($path)))
            ->flatMap(fn ($front) => array_keys($front))
            ->unique()
            ->values()
            ->all();

        return response()->json($unique);
    }

    /**
     * Return all unique values for a given front matter key.
     */
    public function values(string $key)
    {
        $unique = collect(Storage::disk('vault')->allFiles())
            ->filter(fn ($path) => str($path)->endsWith('.md'))
            ->map(fn ($path) => $this->parseFront(Storage::disk('vault')->get($path)))
            ->filter(fn ($front) => array_key_exists($key, $front))
            ->map(fn ($front) => $front[$key])
            ->unique()
            ->values()
            ->all();

        return response()->json($unique);
    }
}
