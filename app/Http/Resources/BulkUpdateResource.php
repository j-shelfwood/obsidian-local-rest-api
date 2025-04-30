<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BulkUpdateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'results' => $this['results'],
        ];
    }
}
