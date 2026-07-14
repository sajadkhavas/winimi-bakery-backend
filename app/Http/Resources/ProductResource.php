<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->slug,
            'slug'            => $this->slug,
            'name'            => $this->name,
            'model'           => $this->model,
            'type'            => $this->subcategory?->slug,
            'category'        => $this->category?->slug,
            'categoryName'    => $this->category?->name,
            'brand'           => $this->brand?->name,
            'brandSlug'       => $this->brand?->slug,
            'country'         => $this->country,
            'usage'           => $this->usage ?? [],
            'priceRange'      => $this->price_range,
            'applications'    => $this->applications ?? [],
            'inStock'         => (bool) $this->in_stock,
            'isFeatured'      => (bool) $this->is_featured,
            'description'     => $this->description,
            'longDescription' => $this->long_description,
            'excerpt'         => $this->excerpt,
            'image'           => $this->image ?: $this->og_image,
            'gallery'         => $this->gallery ?? [],
            'specs'           => $this->specs ?? [],
            'viewCount'       => $this->view_count,
            'rfqCount'        => $this->rfq_count,
            'seo' => [
                'title'       => $this->meta_title ?: "خرید {$this->name} | تول‌مستر",
                'description' => $this->meta_description ?: $this->excerpt ?: $this->description,
                'keywords'    => $this->meta_keywords,
                'schema'      => $this->getProductSchemaAttribute(),
            ],
        ];
    }
}
