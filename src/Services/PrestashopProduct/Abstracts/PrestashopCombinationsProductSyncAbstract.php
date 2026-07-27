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

namespace MoloniOn\Services\PrestashopProduct\Abstracts;

use MoloniOn\Enums\Boolean;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Services\PrestashopProduct\Helpers\Combinations\CreateMappingsAfterPrestaProductCreateOrUpdate;
use MoloniOn\Services\PrestashopProduct\Helpers\Combinations\ProcessAttributesGroup;
use MoloniOn\Services\PrestashopProduct\Helpers\UpdatePrestaProductImage;
use MoloniOn\Services\PrestashopProduct\ProductCombination;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Moloni product with variants -> PrestaShop product with combinations sync.
 */
abstract class PrestashopCombinationsProductSyncAbstract extends PrestashopProductSyncAbstract
{
    /**
     * Product combinations
     *
     * @var ProductCombination[]
     */
    protected $combinations = [];

    /**
     * Run the field setters for a product with combinations.
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    protected function build(): void
    {
        $this->type = 'combinations';

        $this
            ->setReference()
            ->setVisibility()
            ->setImagePath()
            ->setAttributes()
            ->setCombinations()
            ->setName()
            ->setDescription()
            ->setIdentifications()
            ->setHasStock()
            ->setWarehouseId()
            ->setStock()
            ->setPrice();
    }

    /**
     * After save requirements
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    protected function afterSave(): void
    {
        if (!empty($this->categories)) {
            $this->prestashopProduct->deleteCategories();
            $this->prestashopProduct->addToCategories($this->categories);
        }

        if (!empty($this->imagePath) && $this->shouldSyncImage()) {
            new UpdatePrestaProductImage($this->prestashopProduct->id, $this->imagePath);
        }

        // Save combinations
        foreach ($this->combinations as $combination) {
            if ($combination->getCombinationId() > 0) {
                $combination->update();
            } else {
                $combination->insert();
                $combination->updateStock();
            }
        }

        new CreateMappingsAfterPrestaProductCreateOrUpdate($this->moloniProduct, $this->prestashopProduct, $this->combinations);
    }

    //          SETS          //

    /**
     * Set attributes
     *
     * @return $this
     *
     * @throws MoloniProductException
     */
    public function setAttributes(): self
    {
        // Check if Moloni groups exist
        try {
            new ProcessAttributesGroup($this->moloniProduct['propertyGroup']);
        } catch (\PrestaShopException $e) {
            throw new MoloniProductException('Error when creating product attributes', [], [$e->getMessage()]);
        }

        return $this;
    }

    /**
     * Sets product combinations
     *
     * @return $this
     */
    public function setCombinations(): self
    {
        $combinations = [];

        foreach ($this->moloniProduct['variants'] as $variant) {
            if ($variant['visible'] === Boolean::YES) {
                $combinations[] = new ProductCombination($this->prestashopProduct, $this->moloniProduct, $variant, $this->syncFields);
            }
        }

        $this->combinations = $combinations;

        return $this;
    }

    //          GETS          //

    /**
     * Get product combinations
     *
     * @return ProductCombination[]
     */
    public function getCombinations(): array
    {
        return $this->combinations;
    }
}
