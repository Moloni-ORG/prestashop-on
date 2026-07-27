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
use MoloniOn\Services\MoloniProduct\Helpers\UpdateMoloniSimpleProductImage;
use MoloniOn\Tools\ProductAssociations;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Simple (no combinations) PrestaShop -> Moloni product sync.
 */
abstract class MoloniSimpleProductSyncAbstract extends MoloniProductSyncAbstract
{
    /**
     * Run the field setters for a simple product.
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    protected function build(): void
    {
        $this
            ->verifyPrestaProduct()
            ->setReference()
            ->setCoverImage()
            ->setVisibility()
            ->setName()
            ->setSummary()
            ->setHasStock()
            ->setStock()
            ->setPrice()
            ->setType()
            ->setTypeAT()
            ->setTax()
            ->setEcoTax()
            ->setWarehouseId()
            ->setIdentifications()
            ->setMeasurementUnitId();
    }

    /**
     * Create product information to save
     *
     * @return array
     */
    protected function toArray(): array
    {
        $props = [
            'visible' => $this->visibility,
            'type' => $this->type,
            'reference' => $this->reference,
            'name' => $this->name,
            'hasStock' => $this->hasStock,
            'price' => $this->price,
            'summary' => $this->summary,
            'identifications' => $this->identifications,
            'measurementUnitId' => $this->measurementUnitId,
            'productAT' => [
                'productType' => $this->typeAT,
            ],
            'taxes' => [],
            'exemptionReason' => '',
        ];

        if (!$this->shouldSyncVisibility()) {
            unset($props['visible']);
        }

        if (!$this->shouldSyncName()) {
            unset($props['name']);
        }

        if (!$this->shouldSyncDescription()) {
            unset($props['summary']);
        }

        if (!$this->shouldSyncIdentifiers()) {
            unset($props['identifications']);
        }

        if (!$this->shouldSyncPrice()) {
            unset($props['price']);
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

        if ($this->productExists()) {
            $props['productId'] = $this->getMoloniProductId();
        } elseif ($this->warehouseId > 0 && $this->productHasStock()) {
            $props['warehouseId'] = $this->warehouseId;
            $props['warehouses'] = [
                [
                    'warehouseId' => $this->warehouseId,
                    'stock' => $this->stock,
                ],
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
        if (!empty($this->coverImage) && $this->shouldSyncImage()) {
            new UpdateMoloniSimpleProductImage($this->coverImage, $this->getMoloniProductId());
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
        $moloniProductId = $this->getMoloniProductId();
        $prestashopProductId = (int) $this->prestashopProduct->id;

        if ($moloniProductId <= 0 || $prestashopProductId <= 0) {
            return;
        }

        ProductAssociations::deleteByPrestashopId($prestashopProductId);
        ProductAssociations::deleteByMoloniId($moloniProductId);

        ProductAssociations::add(
            $moloniProductId,
            (string) $this->reference,
            0,
            $prestashopProductId,
            (string) $this->prestashopProduct->reference,
            0,
            '',
            Boolean::YES
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
        if (!$this->moloniProduct['deletable'] && !empty($this->moloniProduct['variants']) && $this->productExists()) {
            throw new MoloniProductException('Cannot update product in Moloni ON. Product types do not match', null, ['moloniProductId' => $this->getMoloniProductId(), 'prestashopProductId' => $this->prestashopProduct->id]);
        }
    }
}
