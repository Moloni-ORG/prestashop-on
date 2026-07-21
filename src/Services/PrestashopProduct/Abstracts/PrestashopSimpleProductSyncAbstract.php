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
use MoloniOn\Services\PrestashopProduct\Helpers\UpdatePrestaProductImage;
use MoloniOn\Tools\ProductAssociations;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Simple (no combinations) Moloni -> PrestaShop product sync.
 */
abstract class PrestashopSimpleProductSyncAbstract extends PrestashopProductSyncAbstract
{
    /**
     * Run the field setters for a simple product.
     *
     * @return void
     */
    protected function build(): void
    {
        $this->type = 'standard';

        $this
            ->setReference()
            ->setVisibility()
            ->setImagePath()
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

        $this->mapSimpleAssociation();
    }

    /**
     * (Re)map this simple product to its Moloni counterpart in the associations
     * table (variant and combination = 0), so future syncs match by stored id
     * instead of only by reference.
     *
     * @return void
     */
    protected function mapSimpleAssociation(): void
    {
        $moloniProductId = (int) ($this->moloniProduct['productId'] ?? 0);
        $prestashopProductId = (int) $this->prestashopProduct->id;

        if ($moloniProductId <= 0 || $prestashopProductId <= 0) {
            return;
        }

        ProductAssociations::deleteByPrestashopId($prestashopProductId);
        ProductAssociations::deleteByMoloniId($moloniProductId);

        ProductAssociations::add(
            $moloniProductId,
            (string) ($this->moloniProduct['reference'] ?? ''),
            0,
            $prestashopProductId,
            (string) $this->prestashopProduct->reference,
            0,
            '',
            Boolean::YES
        );
    }
}
