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
use MoloniOn\Services\MoloniProduct\Helpers\CombinationReference;
use MoloniOn\Tools\ProductAssociations;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Represents a single PrestaShop combination as a standalone simple Moloni
 * product.
 *
 * Used for companies without the Product Properties module, where variants
 * cannot exist in Moloni ON. The combination-specific field setters (reference,
 * price, stock, identifications) are overridden to read the combination instead
 * of the parent product, and the resulting product is mapped back to the
 * combination in the associations table (mlVariantId = 0).
 */
abstract class MoloniSimpleFromCombinationSyncAbstract extends MoloniSimpleProductSyncAbstract
{
    /**
     * PrestaShop combination object
     *
     * @var \Combination
     */
    protected $prestashopCombination;

    /**
     * Constructor
     *
     * @param \Product $prestashopProduct
     * @param \Combination $prestashopCombination
     * @param array $moloniProduct Already fetched Moloni product (empty for a create)
     */
    public function __construct(\Product $prestashopProduct, \Combination $prestashopCombination, array $moloniProduct = [])
    {
        parent::__construct($prestashopProduct, $moloniProduct);

        $this->prestashopCombination = $prestashopCombination;
    }

    /**
     * Set product reference (combination reference, with a stable fallback)
     *
     * @return $this
     */
    public function setReference(): self
    {
        $this->reference = CombinationReference::get($this->prestashopProduct, $this->prestashopCombination);

        return $this;
    }

    /**
     * Set product name (parent name decorated with the combination attributes)
     *
     * @return $this
     */
    public function setName(): self
    {
        $name = (string) $this->prestashopProduct->name;

        $attributes = $this->getCombinationAttributesName();

        if ($attributes !== '') {
            $name .= ' - ' . $attributes;
        }

        $this->name = $name;

        return $this;
    }

    /**
     * Set product price (combination price impact)
     *
     * @return $this
     */
    public function setPrice(): self
    {
        $this->price = (float) \Product::getPriceStatic(
            (int) $this->prestashopCombination->id_product,
            true,
            (int) $this->prestashopCombination->id,
            5
        );

        return $this;
    }

    /**
     * Set stock quantity (combination stock)
     *
     * @param float|null $newStock
     *
     * @return $this
     */
    public function setStock(?float $newStock = null): self
    {
        if (!$this->canSyncStock) {
            return $this;
        }

        if ($newStock !== null) {
            $this->stock = $newStock;

            return $this;
        }

        $this->stock = (float) \StockAvailable::getQuantityAvailableByProduct(
            (int) $this->prestashopCombination->id_product,
            (int) $this->prestashopCombination->id
        );

        return $this;
    }

    /**
     * Set product identifications (combination EAN13 / ISBN / UPC)
     *
     * @return $this
     */
    public function setIdentifications(): self
    {
        $identifications = [];

        $isEanFav = false;
        $isIsbnFav = false;
        $isUpcaFav = false;

        if (!empty($this->moloniProduct['identifications'])) {
            foreach ($this->moloniProduct['identifications'] as $identification) {
                switch ($identification['type']) {
                    case 'ISBN':
                        $isIsbnFav = $identification['favorite'];
                        continue 2;
                    case 'EAN13':
                        $isEanFav = $identification['favorite'];
                        continue 2;
                    case 'UPCA':
                        $isUpcaFav = $identification['favorite'];
                        continue 2;
                }

                $identifications[] = $identification;
            }
        }

        if (!empty($this->prestashopCombination->ean13)) {
            $identifications[] = [
                'type' => 'EAN13',
                'text' => $this->prestashopCombination->ean13,
                'favorite' => $isEanFav,
            ];
        }

        if (!empty($this->prestashopCombination->isbn)) {
            $identifications[] = [
                'type' => 'ISBN',
                'text' => $this->prestashopCombination->isbn,
                'favorite' => $isIsbnFav,
            ];
        }

        if (!empty($this->prestashopCombination->upc)) {
            $identifications[] = [
                'type' => 'UPCA',
                'text' => $this->prestashopCombination->upc,
                'favorite' => $isUpcaFav,
            ];
        }

        $this->identifications = $identifications;

        return $this;
    }

    /**
     * Actions run after a save: keep the base image sync and (re)map the
     * combination to the created/updated simple product.
     *
     * @return void
     */
    protected function afterSave(): void
    {
        parent::afterSave();

        $combinationId = (int) $this->prestashopCombination->id;

        ProductAssociations::deleteByCombinationId($combinationId);
        ProductAssociations::add(
            $this->getMoloniProductId(),
            $this->reference,
            0,
            (int) $this->prestashopProduct->id,
            (string) $this->prestashopProduct->reference,
            $combinationId,
            (string) $this->prestashopCombination->reference,
            Boolean::YES
        );
    }

    /**
     * Build the combination's attribute names (e.g. "M, Blue")
     *
     * @return string
     */
    private function getCombinationAttributesName(): string
    {
        $languageId = (int) \Configuration::get('PS_LANG_DEFAULT');

        $rows = $this->prestashopProduct->getAttributeCombinationsById(
            (int) $this->prestashopCombination->id,
            $languageId
        );

        if (empty($rows)) {
            return '';
        }

        $names = [];

        foreach ($rows as $row) {
            if (!empty($row['attribute_name'])) {
                $names[] = $row['attribute_name'];
            }
        }

        return implode(', ', $names);
    }
}
