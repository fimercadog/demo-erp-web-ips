<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'category_id' => $this->category_id,
            'vertical' => $this->vertical,
            'name' => $this->name,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'type' => $this->type,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'status' => $this->status,
            'is_public' => (bool) $this->is_public,
            'display_order' => (int) $this->display_order,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
        ];
    }
}
