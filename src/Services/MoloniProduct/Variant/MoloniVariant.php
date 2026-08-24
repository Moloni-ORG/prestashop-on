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

namespace MoloniOn\Services\MoloniProduct\Variant;

use Combination;
use Image;
use MoloniOn\Enums\Boolean;
use MoloniOn\Enums\ProductVisibility;
use MoloniOn\Enums\SyncFields;
use MoloniOn\Services\MoloniProduct\Helpers\Variants\FindVariant;
use MoloniOn\Tools\Settings;
use Product;

if (!defined('_PS_VERSION_')) {
    exit;
}

class MoloniVariant
{
    /**
     * Moloni parent product
     *
     * @var array
     */
    protected $moloniParentProduct = [];

    /**
     * Moloni variant data
     *
     * @var array
     */
    protected $moloniVariant = [];

    /**
     * Combination
     *
     * @var \Combination|null
     */
    protected $prestashopCombination;

    /**
     * Visibility
     *
     * @var int
     */
    protected $visibility;

    /**
     * Product name
     *
     * @var string
     */
    protected $name;

    /**
     * Parent product name
     *
     * @var string
     */
    protected $parentName;

    /**
     * Product reference
     *
     * @var string
     */
    protected $reference;

    /**
     * Product identifications
     *
     * @var array
     */
    protected $identifications = [];

    /**
     * Product price
     *
     * @var float
     */
    protected $price = 0;

    /**
     * Has stock
     *
     * @var bool
     */
    protected $parentHasStock;

    /**
     * Has stock
     *
     * @var float
     */
    protected $stock = 0;

    /**
     * Warehouse
     *
     * @var int
     */
    protected $warehouseId;

    /**
     * Variant property values
     *
     * @var array
     */
    protected $propertyPairs = [];

    /**
     * Product image
     *
     * @var array
     */
    protected $image = [];

    /**
     * Fields that will be synced
     *
     * @var array
     */
    protected $syncFields;

    /**
     * Whether the parent product is being created now (vs. already existing).
     * Captured at construction because the parent id is only known after its
     * own save, which happens before the variants are created.
     *
     * @var bool
     */
    protected $isNewProduct;

    /**
     * Constructor
     *
     * @param \Combination $prestashopCombination
     * @param string|null $parentName
     * @param array|null $moloniParentProduct
     * @param array|null $propertyPairs
     * @param array|null $syncFields
     */
    public function __construct(
        \Combination $prestashopCombination,
        ?string $parentName = '',
        ?array $moloniParentProduct = [],
        ?array $propertyPairs = [],
        ?array $syncFields = null
    ) {
        $this->prestashopCombination = $prestashopCombination;

        $this->parentName = $parentName;
        $this->moloniParentProduct = $moloniParentProduct;
        $this->propertyPairs = $propertyPairs;
        $this->isNewProduct = empty($moloniParentProduct);

        $this->syncFields = $syncFields;

        $this->init();
    }

    //          PUBLICS          //

    /**
     * Create data
     *
     * @return $this
     */
    public function init(): MoloniVariant
    {
        $this
            ->setReference()
            ->setMoloniVariant()
            ->setParentHasStock()
            ->setName()
            ->setIdentifications()
            ->setPrice()
            ->setStock()
            ->setVisibility()
            ->setImage();

        return $this;
    }

    /**
     * Payload to create this variant (productVariantCreate).
     *
     * The reference is intentionally omitted so Moloni auto-generates it from
     * the parent reference and the property value codes.
     *
     * @return array
     */
    public function toCreateArray(): array
    {
        $props = [
            'visible' => $this->visibility,
            'name' => $this->name,
            'propertyPairs' => $this->propertyPairs,
        ];

        if ($this->shouldSyncPrice()) {
            $props['price'] = $this->price;
        }

        if ($this->shouldSyncIdentifiers()) {
            $props['identifications'] = $this->identifications;
        }

        if ($this->parentHasStock()) {
            $props['warehouseId'] = $this->warehouseId;

            $warehouse = [
                'warehouseId' => $this->warehouseId,
            ];

            // Only seed the initial stock when the whole product is being created
            if ($this->isNewProduct) {
                $warehouse['stock'] = $this->stock;
            }

            $props['warehouses'] = [$warehouse];
        }

        return $props;
    }

    /**
     * Payload to update this (already existing) variant (productUpdate).
     *
     * @return array
     */
    public function toUpdateArray(): array
    {
        $props = [
            'productId' => $this->getMoloniVariantId(),
            'visible' => $this->visibility,
            'name' => $this->name,
        ];

        if ($this->shouldSyncPrice()) {
            $props['price'] = $this->price;
        }

        if ($this->shouldSyncIdentifiers()) {
            $props['identifications'] = $this->identifications;
        }

        return $props;
    }

    /**
     * Whether the matched Moloni variant differs from what we would send, i.e.
     * whether a productUpdate is actually worth issuing. Errs towards updating:
     * any field we can't confidently compare returns true.
     *
     * @return bool
     */
    public function needsUpdate(): bool
    {
        // Nothing to compare against -> must update
        if (empty($this->moloniVariant)) {
            return true;
        }

        if ((int) ($this->moloniVariant['visible'] ?? -1) !== (int) $this->visibility) {
            return true;
        }

        if (($this->moloniVariant['name'] ?? null) !== $this->name) {
            return true;
        }

        if ($this->shouldSyncPrice()
            && abs((float) ($this->moloniVariant['price'] ?? 0) - (float) $this->price) > 0.00001) {
            return true;
        }

        if ($this->shouldSyncIdentifiers()
            && $this->identificationsChanged($this->moloniVariant['identifications'] ?? [])) {
            return true;
        }

        return false;
    }

    /**
     * Compare the current Moloni identifications against the ones we would send.
     *
     * @param array $current
     *
     * @return bool
     */
    private function identificationsChanged(array $current): bool
    {
        if (count($current) !== count($this->identifications)) {
            return true;
        }

        $normalize = static function (array $items): array {
            $out = [];

            foreach ($items as $item) {
                $out[] = ($item['type'] ?? '') . '|' . ($item['text'] ?? '') . '|' . ((int) ($item['favorite'] ?? 0));
            }

            sort($out);

            return $out;
        };

        return $normalize($current) !== $normalize($this->identifications);
    }

    //          SETS          //

    /**
     * Finds moloni variant
     *
     * @return $this
     */
    public function setMoloniVariant(): MoloniVariant
    {
        if ($this->parentExists()) {
            $variant = (new FindVariant(
                $this->getPrestashopCombinationId(),
                $this->reference,
                $this->moloniParentProduct['variants'] ?? [],
                $this->propertyPairs
            ))->handle();

            if (!empty($variant)) {
                $this->moloniVariant = $variant;
            }
        }

        return $this;
    }

    /**
     * Variant visibility
     *
     * @return MoloniVariant
     */
    public function setVisibility(): MoloniVariant
    {
        $this->visibility = ProductVisibility::VISIBLE;

        return $this;
    }

    /**
     * Variant name
     *
     * @return MoloniVariant
     */
    public function setName(): MoloniVariant
    {
        switch (true) {
            case !empty($this->moloniVariant['name']):
                $this->name = $this->moloniVariant['name'];
                break;
            case !empty($this->parentName):
                $this->name = $this->parentName;
                break;
            case !empty($this->reference):
                $this->name = $this->reference;
                break;
            default:
                $this->name = 'Variant';
                break;
        }

        return $this;
    }

    /**
     * Variant reference
     *
     * @return MoloniVariant
     */
    public function setReference(): MoloniVariant
    {
        $reference = $this->prestashopCombination->reference;

        if (empty($reference)) {
            $reference = '';
        }

        $this->reference = $reference;

        return $this;
    }

    /**
     * Set variant identifications
     *
     * @return MoloniVariant
     */
    public function setIdentifications(): MoloniVariant
    {
        $identifications = [];

        $isEanFav = false;
        $isIsbnFav = false;
        $isUpcaFav = false;

        if (isset($this->moloniVariant['identifications']) && !empty($this->moloniVariant['identifications'])) {
            foreach ($this->moloniVariant['identifications'] as $identification) {
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
     * Set variant price
     *
     * @return MoloniVariant
     */
    public function setPrice(): MoloniVariant
    {
        $this->price = \Product::getPriceStatic(
            $this->prestashopCombination->id_product,
            false,
            $this->prestashopCombination->id
        );

        return $this;
    }

    /**
     * Set warehouse id
     *
     * @param int|null $warehouseId
     *
     * @return MoloniVariant
     */
    public function setWarehouseId(?int $warehouseId = null): MoloniVariant
    {
        $this->warehouseId = (int) ($warehouseId ?? Settings::get('syncStockToMoloniWarehouse') ?? 0);

        return $this;
    }

    /**
     * Set if variants has stock
     *
     * @param bool|null $parentHasStock
     *
     * @return MoloniVariant
     */
    public function setParentHasStock(?bool $parentHasStock = null): MoloniVariant
    {
        $this->parentHasStock = $parentHasStock ?? (bool) Boolean::YES;

        return $this;
    }

    /**
     * Set variant stock
     *
     * @param float|null $newStock
     *
     * @return MoloniVariant
     */
    public function setStock(?float $newStock = null): MoloniVariant
    {
        if ($newStock !== null) {
            $this->stock = $newStock;

            return $this;
        }

        $this->stock = \StockAvailable::getQuantityAvailableByProduct(
            $this->prestashopCombination->id_product,
            $this->prestashopCombination->id
        );

        return $this;
    }

    /**
     * Set variant image
     *
     * @return $this
     */
    public function setImage(): MoloniVariant
    {
        $languageId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $shopId = (int) \Shop::getContextShopID();

        $image = \Image::getBestImageAttribute(
            $shopId,
            $languageId,
            $this->prestashopCombination->id_product,
            $this->prestashopCombination->id
        );

        if ($image) {
            $this->image = $image;
        }

        return $this;
    }

    /**
     * Set variant property pairs
     *
     * @param array|null $propertyPairs
     *
     * @return MoloniVariant
     */
    public function setPropertyPairs(?array $propertyPairs = []): MoloniVariant
    {
        $this->propertyPairs = $propertyPairs;

        return $this;
    }

    /**
     * Set Moloni parent
     *
     * @param array $moloniParent
     *
     * @return MoloniVariant
     */
    public function setMoloniParent(array $moloniParent): MoloniVariant
    {
        $this->moloniParentProduct = $moloniParent;

        return $this;
    }

    /**
     * Store the Moloni variant returned by a create/update mutation
     *
     * @param array $moloniVariant
     *
     * @return MoloniVariant
     */
    public function setMoloniVariantData(array $moloniVariant): MoloniVariant
    {
        $this->moloniVariant = $moloniVariant;

        return $this;
    }

    //          GETS          //

    /**
     * Get parent id
     *
     * @return int
     */
    public function getMoloniParentId(): int
    {
        if (empty($this->moloniParentProduct)) {
            return 0;
        }

        return (int) $this->moloniParentProduct['productId'];
    }

    /**
     * Get variant id
     *
     * @return int
     */
    public function getMoloniVariantId(): int
    {
        if (empty($this->moloniVariant)) {
            return 0;
        }

        return (int) $this->moloniVariant['productId'];
    }

    /**
     * Get combination id
     *
     * @return int
     */
    public function getPrestashopCombinationId(): int
    {
        return $this->prestashopCombination->id;
    }

    /**
     * Get variant data
     *
     * @return array
     */
    public function getMoloniVariant(): array
    {
        if (empty($this->moloniVariant)) {
            return [];
        }

        return $this->moloniVariant;
    }

    /**
     * Get variant property pairs
     *
     * @return array
     */
    public function getPropertyPairs(): array
    {
        return $this->propertyPairs;
    }

    /**
     * Get variant reference
     *
     * @return string
     */
    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * Get variant image
     *
     * @return array
     */
    public function getImage(): array
    {
        return $this->image;
    }

    //          VERIFICATIONS          //

    /**
     * Should sync variant price
     *
     * @return bool
     */
    protected function shouldSyncPrice(): bool
    {
        return !$this->variantExists() || in_array(SyncFields::PRICE, $this->syncFields, true);
    }

    /**
     * Should sync product identifiers (ISBN, EAN)
     *
     * @return bool
     */
    protected function shouldSyncIdentifiers(): bool
    {
        return in_array(SyncFields::IDENTIFIERS, $this->syncFields, true);
    }

    //          Auxiliary          //

    /**
     * Checks if product has stock
     *
     * @return bool
     */
    protected function parentHasStock(): bool
    {
        return $this->parentHasStock;
    }

    /**
     * Checks if product has stock
     *
     * @return bool
     */
    protected function parentExists(): bool
    {
        return $this->getMoloniParentId() > 0;
    }

    /**
     * Check if current variant exists
     *
     * @return bool
     */
    protected function variantExists(): bool
    {
        return $this->getMoloniVariantId() > 0;
    }
}
