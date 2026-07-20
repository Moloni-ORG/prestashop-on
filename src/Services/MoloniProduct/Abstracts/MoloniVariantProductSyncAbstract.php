<?php

/**
 * 2025 - Moloni.com
 *
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Moloni
 * @copyright Moloni
 * @license   https://creativecommons.org/licenses/by-nd/4.0/
 *
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace MoloniOn\Services\MoloniProduct\Abstracts;

use MoloniOn\Enums\Boolean;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Services\MoloniProduct\Helpers\Variants\CreateMappingsAfterMoloniProductCreateOrUpdate;
use MoloniOn\Services\MoloniProduct\Helpers\Variants\FindOrCreatePropertyGroup;
use MoloniOn\Services\MoloniProduct\Helpers\Variants\GetOrUpdatePropertyGroup;
use MoloniOn\Services\MoloniProduct\Helpers\Variants\UpdateMoloniVariantsProductImage;
use MoloniOn\Services\MoloniProduct\Variant\MoloniVariant;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * PrestaShop product with combinations -> Moloni product with variants sync.
 */
abstract class MoloniVariantProductSyncAbstract extends MoloniProductSyncAbstract
{
    /**
     * Product property group
     *
     * @var array
     */
    protected $propertyGroup = [];

    /**
     * Product variants
     *
     * @var MoloniVariant[]
     */
    protected $variants = [];

    /**
     * Run the field setters for a product with variants.
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    protected function build(): void
    {
        // Products with combinations manage stock by default
        $this->hasStock = true;

        $this
            ->verifyPrestaProduct()
            ->setReference()
            ->setVisibility()
            ->setName()
            ->setSummary()
            ->setHasStock()
            ->setPrice()
            ->setType()
            ->setTypeAT()
            ->setTax()
            ->setEcoTax()
            ->setWarehouseId()
            ->setIdentifications()
            ->setMeasurementUnitId()
            ->setCoverImage()
            ->setPropertyGroup()
            ->setVariants();
    }

    /**
     * Create product information to save
     *
     * @return array
     */
    protected function toArray(): array
    {
        $props = [
            'type' => $this->type,
            'reference' => $this->reference,
            'measurementUnitId' => $this->measurementUnitId,
            'productAT' => [
                'productType' => $this->typeAT,
            ],
            'variants' => [],
            'taxes' => [],
            'exemptionReason' => '',
        ];

        if ($this->shouldSyncVisibility()) {
            $props['visible'] = $this->visibility;
        }

        if ($this->shouldSyncName()) {
            $props['name'] = $this->name;
        }

        if ($this->shouldSyncDescription()) {
            $props['summary'] = $this->summary;
        }

        if ($this->shouldSyncIdentifiers()) {
            $props['identifications'] = $this->identifications;
        }

        if ($this->shouldSyncPrice()) {
            $props['price'] = $this->price;
        }

        if (!empty($this->category)) {
            $props['productCategoryId'] = $this->category->getProductCategoryId();
        }

        if (!empty($this->tax)) {
            $props['taxes'][] = $this->tax->toArray();
        }

        if (!empty($this->exemptionReason)) {
            $props['exemptionReason'] = $this->exemptionReason;
        }

        if (!empty($this->propertyGroup)) {
            $props['propertyGroupId'] = $this->propertyGroup['propertyGroupId'];
        }

        foreach ($this->variants as $variant) {
            $props['variants'][] = $variant->toArray();
        }

        if ($this->productExists()) {
            $props['productId'] = $this->getMoloniProductId();

            // Check for unused variants that cannot be deleted
            foreach ($this->moloniProduct['variants'] as $existingVariant) {
                foreach ($props['variants'] as $newVariant) {
                    if (!isset($newVariant['productId'])) {
                        continue;
                    }

                    if ($existingVariant['productId'] === $newVariant['productId']) {
                        continue 2;
                    }
                }

                // If we cannot delete variant, set it as invisible
                if ($existingVariant['deletable'] === false) {
                    $props['variants'][] = [
                        'productId' => $existingVariant['productId'],
                        'visible' => Boolean::NO,
                    ];
                }
            }
        } elseif ($this->productHasStock()) {
            $props['hasStock'] = $this->hasStock;
            $props['warehouseId'] = $this->warehouseId;
            $props['warehouses'] = [
                'warehouseId' => $this->warehouseId,
            ];
        }

        return $props;
    }

    /**
     * Actions run after a save
     *
     * @return void
     */
    protected function afterSave(): void
    {
        // Update all variants values
        foreach ($this->variants as $variant) {
            // Update product with the one just added
            $variant->setMoloniParent($this->moloniProduct);

            // If was an insert, we need to get the id
            if ($variant->getMoloniVariantId() === 0) {
                $variant->setMoloniVariant();
            }
        }

        if (!empty($this->coverImage) && $this->shouldSyncImage()) {
            new UpdateMoloniVariantsProductImage($this->coverImage, $this->moloniProduct, $this->variants);
        }

        new CreateMappingsAfterMoloniProductCreateOrUpdate(
            $this->prestashopProduct,
            $this->moloniProduct,
            $this->variants
        );
    }

    /**
     * Actions run before an update
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    protected function beforeUpdate(): void
    {
        if (!$this->moloniProduct['deletable'] && empty($this->moloniProduct['variants']) && $this->productExists()) {
            throw new MoloniProductException('Cannot update product in Moloni ON. Product types do not match', null, ['moloniProductId' => $this->getMoloniProductId(), 'prestashopProductId' => $this->prestashopProduct->id]);
        }
    }

    //          SETS          //

    /**
     * Set property group
     *
     * @return $this
     *
     * @throws MoloniProductException
     */
    public function setPropertyGroup(): self
    {
        if ($this->productExists()) {
            $targetId = $this->moloniProduct['propertyGroup']['propertyGroupId'] ?? '';
        } else {
            $targetId = '';
        }

        if (empty($targetId)) {
            /*
             * Find or create the most suitable group for this new product
             */
            $this->propertyGroup = (new FindOrCreatePropertyGroup($this->prestashopProduct))->handle();
        } else {
            /*
             * Product already exists, so it has property group assigned
             * So we need to get the property group and update it if needed
             */
            $this->propertyGroup = (new GetOrUpdatePropertyGroup($this->prestashopProduct, $targetId))->handle();
        }

        return $this;
    }

    /**
     * Set product variants
     *
     * @return $this
     */
    public function setVariants(): self
    {
        $prestashopCombinationsQuery = $this->prestashopProduct->getAttributeCombinations(null, false);

        foreach ($prestashopCombinationsQuery as $combinationQuery) {
            $combination = new \Combination(
                $combinationQuery['id_product_attribute'],
                (int) \Configuration::get('PS_LANG_DEFAULT')
            );

            $builder = new MoloniVariant(
                $combination,
                $this->name,
                $this->moloniProduct,
                $this->propertyGroup['variants'][(int) $combination->id] ?? [],
                $this->syncFields
            );

            $builder
                ->setParentHasStock($this->hasStock)
                ->setWarehouseId($this->warehouseId);

            $this->variants[] = $builder;
        }

        return $this;
    }

    //          GETS          //

    /**
     * Moloni variant getter
     *
     * @param int|null $combinationId Prestashop combination id
     *
     * @return array
     */
    public function getVariant(?int $combinationId = 0): array
    {
        foreach ($this->variants as $variant) {
            if ($variant->getPrestashopCombinationId() === $combinationId) {
                return $variant->getMoloniVariant();
            }
        }

        return [];
    }
}
