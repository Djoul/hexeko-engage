<?php

namespace App\Integrations\Vouchers\Amilon\Traits;

use App\Integrations\Vouchers\Amilon\DTO\ProductDTO;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait ProductAccessorsAndHelpers
{
    /**
     * Convert the model to a ProductDTO.
     */
    public function toDTO(): ProductDTO
    {
        return new ProductDTO(
            name: $this->name,
            productCode: $this->product_code ?? '',
            category_id: $this->category_id,
            merchant_id: $this->merchant_id,
            price: $this->price,
            netPrice: $this->net_price,
            discount: $this->discount,
            currency: $this->currency,
            country: $this->country,
            description: $this->description,
            image_url: $this->image_url,
        );
    }

    /**
     * Create a new product from a ProductDTO.
     */
    public static function fromDTO(ProductDTO $dto): self
    {
        return self::create([
            'product_code' => $dto->productCode,
            'name' => $dto->name,
            'category_id' => $dto->category_id ?? null, // Category ID will be set separately if needed
            'merchant_id' => $dto->merchant_id,
            'price' => $dto->price,
            'net_price' => $dto->netPrice,
            'discount' => $dto->discount,
            'currency' => $dto->currency,
            'country' => $dto->country,
            'description' => $dto->description,
            'image_url' => $dto->image_url,
        ]);
    }

    /**
     * Update or create a product from a ProductDTO.
     */
    public static function updateOrCreateFromDTO(ProductDTO $dto): self
    {
        return self::updateOrCreate(
            ['product_code' => $dto->productCode, 'merchant_id' => $dto->merchant_id],
            [
                'merchant_id' => $dto->merchant_id,
                'product_code' => $dto->productCode,
                'name' => $dto->name,
                'category_id' => $dto->category_id ?? null,
                'price' => $dto->price,
                'net_price' => $dto->netPrice,
                'discount' => $dto->discount,
                'currency' => $dto->currency,
                'country' => $dto->country,
                'description' => $dto->description,
                'image_url' => $dto->image_url,
            ]
        );
    }

    /**
     * Get category attribute for backward compatibility.
     * Returns the name of the associated category.
     */
    public function getCategoryAttribute(): ?string
    {
        // Check if relation is already loaded to avoid N+1
        $category = $this->relationLoaded('category') ? $this->getRelation('category') : $this->category()->first();

        return $category && property_exists($category, 'name') ? $category->name : null;
    }

    /**
     * Handle discount attribute with Laravel's new Attribute syntax.
     * Convert between percentage (6.67%) and stored integer (667).
     */
    protected function discount(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): ?float => $value === null ? null : round($value / 10, 2),
            set: fn (?float $value): ?int => $value === null ? null : (int) round($value * 100)
        );
    }
}
