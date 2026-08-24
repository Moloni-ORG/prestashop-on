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

use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Enums\Boolean;
use MoloniOn\Exceptions\MoloniApiException;
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

        if ($this->productExists()) {
            $props['productId'] = $this->getMoloniProductId();
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
        // Snapshot of the variants that already existed in Moloni (empty on a create)
        $existingVariants = $this->moloniProduct['variants'] ?? [];

        // Create new / update changed variants and rebuild the live variant list
        $keptVariantIds = $this->syncVariants($existingVariants);

        if (!empty($this->coverImage) && $this->shouldSyncImage()) {
            new UpdateMoloniVariantsProductImage($this->coverImage, $this->moloniProduct, $this->variants);
        }

        new CreateMappingsAfterMoloniProductCreateOrUpdate(
            $this->prestashopProduct,
            $this->moloniProduct,
            $this->variants
        );

        // Delete/hide variants removed from PrestaShop last, so a failure here does
        // not skip the association rebuild for the variants we just synced
        $this->removeUnusedVariants($existingVariants, $keptVariantIds);
    }

    /**
     * Create/update PrestaShop combinations as Moloni variants and rebuild the
     * live variant list from what was actually synced.
     *
     * The parent product is already saved at this point. New variants are added
     * with productVariantCreate (without resending their siblings) and existing
     * variants are updated only when something changed. Matching is done against
     * the variants that existed before this sync, so a new combination can never
     * be matched onto a sibling created during this same run.
     *
     * @param array $existingVariants
     *
     * @return array Moloni ids of the variants that were kept (updated or created)
     *
     * @throws MoloniProductException
     */
    protected function syncVariants(array $existingVariants): array
    {
        // Match only against the pre-sync variants, never against siblings created now
        $parentForMatching = $this->moloniProduct;
        $parentForMatching['variants'] = $existingVariants;

        $keptVariantIds = [];
        $syncedVariants = [];

        foreach ($this->variants as $variant) {
            $variant->setMoloniParent($parentForMatching);

            if ($variant->getMoloniVariantId() === 0) {
                $variant->setMoloniVariant();
            }

            if ($variant->getMoloniVariantId() > 0) {
                if ($variant->needsUpdate()) {
                    $this->updateVariant($variant);
                }
            } else {
                $this->createVariant($variant);
            }

            $keptVariantIds[] = $variant->getMoloniVariantId();
            $syncedVariants[] = $variant->getMoloniVariant();
        }

        // Rebuild the list from the synced variants (kept + created), dropping removed
        // ones so later steps (image sync) never reference a deleted variant id
        $this->moloniProduct['variants'] = array_values(array_filter($syncedVariants));

        return $keptVariantIds;
    }

    /**
     * Create a single variant on the (already saved) parent product.
     *
     * @param MoloniVariant $variant
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    protected function createVariant(MoloniVariant $variant): void
    {
        $variables = [
            'productId' => $this->getMoloniProductId(),
            'data' => $variant->toCreateArray(),
        ];

        try {
            $mutation = MoloniApiClient::products()->mutationProductVariantCreate($variables);

            $createdVariant = $mutation['data']['productVariantCreate']['data'] ?? [];

            if (empty($createdVariant['productId'])) {
                throw new MoloniProductException('Error creating variant for product ({0})', ['{0}' => $this->reference], ['mutation' => $mutation, 'props' => $variables]);
            }
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Error creating variant for product ({0})', ['{0}' => $this->reference], $e->getData());
        }

        // Make the new variant available to the following steps (matching, images, mappings)
        $variant->setMoloniVariantData($createdVariant);
    }

    /**
     * Update a single existing variant.
     *
     * @param MoloniVariant $variant
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    protected function updateVariant(MoloniVariant $variant): void
    {
        $variables = [
            'data' => $variant->toUpdateArray(),
        ];

        try {
            $mutation = MoloniApiClient::products()->mutationProductUpdate($variables);

            $updatedVariant = $mutation['data']['productUpdate']['data'] ?? [];

            if (empty($updatedVariant['productId'])) {
                throw new MoloniProductException('Error updating variant ({0})', ['{0}' => $variant->getMoloniVariantId()], ['mutation' => $mutation, 'props' => $variables]);
            }
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Error updating variant ({0})', ['{0}' => $variant->getMoloniVariantId()], $e->getData());
        }
    }

    /**
     * Delete (or hide, when not deletable) the Moloni variants that no longer
     * exist as PrestaShop combinations.
     *
     * @param array $existingVariants
     * @param array $keptVariantIds
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    protected function removeUnusedVariants(array $existingVariants, array $keptVariantIds): void
    {
        $toDelete = [];

        foreach ($existingVariants as $existingVariant) {
            $variantId = (int) $existingVariant['productId'];

            if (in_array($variantId, $keptVariantIds, true)) {
                continue;
            }

            // If it can't be deleted, hide it instead
            if ($existingVariant['deletable'] === false) {
                try {
                    MoloniApiClient::products()->mutationProductUpdate([
                        'data' => [
                            'productId' => $variantId,
                            'visible' => Boolean::NO,
                        ],
                    ]);
                } catch (MoloniApiException $e) {
                    throw new MoloniProductException('Error hiding unused variant ({0})', ['{0}' => $variantId], $e->getData());
                }

                continue;
            }

            $toDelete[] = $variantId;
        }

        if (empty($toDelete)) {
            return;
        }

        try {
            MoloniApiClient::products()->mutationProductDelete(['productId' => $toDelete]);
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Error deleting unused variants ({0})', ['{0}' => implode(', ', $toDelete)], $e->getData());
        }
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
