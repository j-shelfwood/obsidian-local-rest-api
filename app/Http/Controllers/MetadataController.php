<?php

namespace App\Http\Controllers;

use App\Http\Resources\PrimitiveResource;
use App\Services\LocalVaultService;
use Illuminate\Http\Request;
use Symfony\Component\Yaml\Yaml;

class MetadataController extends Controller
{
    protected LocalVaultService $vault;

    public function __construct(LocalVaultService $vault)
    {
        $this->vault = $vault;
    }

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
    public function keys(Request $request)
    {
        $unique = collect($this->vault->allFiles())
            ->filter(fn ($path) => str($path)->endsWith('.md'))
            ->map(fn ($path) => $this->parseFront($this->vault->get($path)))
            ->flatMap(fn ($front) => array_keys($front))
            ->unique()
            ->values()
            ->all();

        return PrimitiveResource::collection($unique);
    }

    /**
     * Return all unique values for a given front matter key.
     */
    public function values(Request $request, string $key)
    {
        $unique = collect($this->vault->allFiles())
            ->filter(fn ($path) => str($path)->endsWith('.md'))
            ->map(fn ($path) => $this->parseFront($this->vault->get($path)))
            ->filter(fn ($front) => array_key_exists($key, $front))
            ->map(fn ($front) => $front[$key])
            ->unique()
            ->values()
            ->all();

        return PrimitiveResource::collection($unique);
    }
}
