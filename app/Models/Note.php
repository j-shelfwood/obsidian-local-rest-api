<?php

namespace App\Models;

class Note
{
    public string $path;

    public array $front_matter;

    public string $content;

    public function __construct(string $path, array $front_matter, string $content)
    {
        $this->path = $path;
        $this->front_matter = $front_matter;
        $this->content = $content;
    }

    public static function fromParsed(string $path, array $parsed): self
    {
        return new self(
            $path,
            $parsed['front_matter'] ?? [],
            $parsed['content'] ?? ''
        );
    }
}
