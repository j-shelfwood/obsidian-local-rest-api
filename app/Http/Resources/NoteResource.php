<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'path' => $this->path,
            'front_matter' => $this->front_matter,
            'content' => $this->content,
        ];
    }
}
