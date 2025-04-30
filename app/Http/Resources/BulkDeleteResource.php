<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BulkDeleteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'deleted' => $this['deleted'],
            'notFound' => $this['notFound'],
        ];
    }
}
