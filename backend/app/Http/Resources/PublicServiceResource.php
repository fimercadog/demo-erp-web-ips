<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Vista pública de un servicio/procedimiento: lo que puede ver un visitante anónimo en la web. */
class PublicServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'type' => $this->type,
            'vertical' => $this->vertical,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
        ];
    }
}
