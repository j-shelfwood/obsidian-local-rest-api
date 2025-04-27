<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class LocalVaultService
{
    protected $disk;

    public function __construct()
    {
        $this->disk = Storage::disk('vault');
    }

    public function allFiles(): array
    {
        return $this->disk->allFiles();
    }

    public function files(string $directory = '.', bool $recursive = true): array
    {
        return $this->disk->files($directory, $recursive);
    }

    public function exists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    public function get(string $path): string
    {
        return $this->disk->get($path);
    }

    public function put(string $path, string $content): void
    {
        $this->disk->put($path, $content);
    }

    public function delete(string $path): void
    {
        $this->disk->delete($path);
    }

    public function makeDirectory(string $path): void
    {
        $this->disk->makeDirectory($path);
    }

    public function path(string $path): string
    {
        return $this->disk->path($path);
    }
}
