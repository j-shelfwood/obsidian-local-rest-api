<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

class FrontMatterController extends Controller
{
    /**
     * Parse the YAML front matter from raw note content.
     */
    protected function parseFront(string $raw): array
    {
        if (substr($raw, 0, 3) === '---') {
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
        $keys = [];
        foreach (Storage::disk('vault')->allFiles() as $path) {
            if (! str_ends_with($path, '.md')) {
                continue;
            }
            $raw = Storage::disk('vault')->get($path);
            $front = $this->parseFront($raw);
            $keys = array_merge($keys, array_keys($front));
        }
        $unique = array_unique($keys);

        return response()->json(array_values($unique));
    }

    /**
     * Return all unique values for a given front matter key.
     */
    public function values(string $key)
    {
        $values = [];
        foreach (Storage::disk('vault')->allFiles() as $path) {
            if (! str_ends_with($path, '.md')) {
                continue;
            }
            $raw = Storage::disk('vault')->get($path);
            $front = $this->parseFront($raw);
            if (isset($front[$key])) {
                $values[] = $front[$key];
            }
        }
        $unique = array_unique($values);

        return response()->json(array_values($unique));
    }
}
