<?php

namespace App\Services;

use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\MenuProduct;
use InvalidArgumentException;

class TenantIsolationService
{
    /**
     * Validate that a category strictly belongs to the specified establishment.
     *
     * @param MenuCategory|string $category
     * @param Establishment|string $establishment
     * @return MenuCategory
     * @throws InvalidArgumentException
     */
    public function validateCategoryBelongsToEstablishment(MenuCategory|string $category, Establishment|string $establishment): MenuCategory
    {
        $categoryModel = is_string($category) ? MenuCategory::findOrFail($category) : $category;
        $establishmentId = is_string($establishment) ? $establishment : $establishment->id;

        if ($categoryModel->establishment_id !== $establishmentId) {
            throw new InvalidArgumentException(
                "Aislamiento violado: La categoría [{$categoryModel->id}] no pertenece al establecimiento [{$establishmentId}]."
            );
        }

        return $categoryModel;
    }

    /**
     * Validate that a product strictly belongs to the specified establishment.
     *
     * @param MenuProduct|string $product
     * @param Establishment|string $establishment
     * @return MenuProduct
     * @throws InvalidArgumentException
     */
    public function validateProductBelongsToEstablishment(MenuProduct|string $product, Establishment|string $establishment): MenuProduct
    {
        $productModel = is_string($product) ? MenuProduct::findOrFail($product) : $product;
        $establishmentId = is_string($establishment) ? $establishment : $establishment->id;

        if ($productModel->establishment_id !== $establishmentId) {
            throw new InvalidArgumentException(
                "Aislamiento violado: El producto [{$productModel->id}] no pertenece al establecimiento [{$establishmentId}]."
            );
        }

        return $productModel;
    }

    /**
     * Validate that incoming product creation/update payload respects tenant boundaries.
     *
     * @param array $productData
     * @param string $establishmentId
     * @return void
     * @throws InvalidArgumentException
     */
    public function validateProductDataIntegrity(array $productData, string $establishmentId): void
    {
        if (isset($productData['establishment_id']) && $productData['establishment_id'] !== $establishmentId) {
            throw new InvalidArgumentException(
                "Inconsistencia multi-tenant: el payload especifica establecimiento [{$productData['establishment_id']}], pero la operación pertenece a [{$establishmentId}]."
            );
        }

        if (isset($productData['category_id'])) {
            $this->validateCategoryBelongsToEstablishment($productData['category_id'], $establishmentId);
        }
    }
}
