<?php

namespace App\Integrations\Vouchers\Amilon\Traits;

use App\Integrations\Vouchers\Amilon\DTO\MerchantDTO;

trait MerchantAccessorsAndHelpers
{
    /**
     * Convert the model to a MerchantDTO.
     */
    public function toDTO(): MerchantDTO
    {
        return new MerchantDTO(
            name: $this->name,
            country: $this->country,
            merchant_id: $this->merchant_id,
            description: $this->description,
            image_url: $this->image_url,
        );
    }

    /**
     * Create a new merchant from a MerchantDTO.
     */
    public static function fromDTO(MerchantDTO $dto): self
    {
        return self::create([
            'name' => $dto->name,
            'country' => $dto->country,
            'merchant_id' => $dto->merchant_id,
            'description' => $dto->description,
            'image_url' => $dto->image_url,
        ]);
    }

    /**
     * Update or create a merchant from a MerchantDTO.
     */
    public static function updateOrCreateFromDTO(MerchantDTO $dto): self
    {

        return self::updateOrCreate(
            ['merchant_id' => $dto->merchant_id],
            [
                'name' => $dto->name,
                'country' => $dto->country,
                'description' => $dto->description,
                'image_url' => $dto->image_url,
            ]
        );
    }

    /**
     * Get retailer_id (alias for merchant_id for backward compatibility).
     */
    public function getRetailerIdAttribute(): ?string
    {
        return $this->merchant_id;
    }

    /**
     * Get category attribute for backward compatibility.
     * Returns the name of the first category.
     */
    public function getCategoryAttribute(): ?string
    {
        $firstCategory = $this->categories->first();

        return $firstCategory ? $firstCategory->name : null;
    }
}
