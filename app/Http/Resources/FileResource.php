<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Return an object with path, last_modified, created_at, size, and mime_type.
     */
    public function toArray($request)
    {
        $filePath = (string) $this->resource;
        $absolutePath = Storage::disk('vault')->path($filePath);

        return [
            'path' => $filePath,
            'last_modified' => Carbon::createFromTimestamp(Storage::disk('vault')->lastModified($filePath))->toIso8601String(),
            'created_at' => file_exists($absolutePath) ? Carbon::createFromTimestamp(filectime($absolutePath))->toIso8601String() : null,
            'size' => file_exists($absolutePath) ? Storage::disk('vault')->size($filePath) : null, // Ensure file exists before getting size
            'mime_type' => file_exists($absolutePath) ? mime_content_type($absolutePath) : null,
        ];
    }
}
