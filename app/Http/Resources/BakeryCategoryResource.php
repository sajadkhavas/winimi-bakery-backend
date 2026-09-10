<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BakeryCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image_path
                ? Storage::disk('public')->url($this->image_path)
                : null,
            'imageAlt' => $this->image_path
                ? (filled($this->image_alt) ? trim((string) $this->image_alt) : $this->name)
                : null,
            'productCount' => $this->whenCounted('products'),
            'seo' => [
                'title' => $this->meta_title ?: $this->name,
                'description' => $this->meta_description ?: $this->description,
            ],
        ];
    }
}
