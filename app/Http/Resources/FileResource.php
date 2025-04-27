<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Return the path string itself for file resources.
     */
    public function toArray($request)
    {
        return (string) $this->resource;
    }
}
