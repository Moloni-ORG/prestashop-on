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
use MoloniOn\Enums\ProductVisibility;
use MoloniOn\Enums\SyncFields;
use MoloniOn\Exceptions\Product\MoloniProductCategoryException;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Helpers\Stock;
use MoloniOn\Helpers\Warehouse;
use MoloniOn\MoloniContext;
use MoloniOn\Services\PrestashopProduct\Helpers\FindTaxGroupFromMoloniTax;
use MoloniOn\Services\PrestashopProduct\Helpers\GetPrestashopCategoriesFromMoloniCategoryId;
use MoloniOn\Services\PrestashopProduct\Interfaces\PrestashopProductServiceInterface;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Shared logic for the Moloni -> PrestaShop product sync services.
 *
 * Holds every field setter, the save (create/update) calls and the logging,
 * which used to be duplicated between the simple and combinations builders.
 */
abstract class PrestashopProductSyncAbstract implements PrestashopProductServiceInterface
{
    /**
     * Moloni product (source)
     *
     * @var array
     */
    protected $moloniProduct;

    /**
     * PrestaShop product (target)
     *
     * @var \Product
     */
    protected $prestashopProduct;

    /**
     * Visibility
     *
     * @var string
     */
    protected $visibility;

    /**
     * Product name
     *
     * @var string
     */
    protected $name;

    /**
     * Product reference
     *
     * @var string
     */
    protected $reference;

    /**
     * Product summary
     *
     * @var string
     */
    protected $description;

    /**
     * Product category
     *
     * @var array
     */
    protected $categories = [];

    /**
     * Product isbn
     *
     * @var string
     */
    protected $isbn = '';

    /**
     * Product ean13
     *
     * @var string
     */
    protected $ean13 = '';

    /**
     * Product UPC-A
     *
     * @var string
     */
    protected $upc = '';

    /**
     * Product price
     *
     * @var float
     */
    protected $price = 0.0;

    /**
     * Has stock
     *
     * @var bool
     */
    protected $hasStock = false;

    /**
     * Warehouse
     *
     * @var int
     */
    protected $warehouseId = 0;

    /**
     * Stock quantity
     *
     * @var float
     */
    protected $stock = 0;

    /**
     * Product tax
     *
     * @var int|null
     */
    protected $taxRulesGroupId;

    /**
     * Product type
     *
     * @var string
     */
    protected $type = '';

    /**
     * Product image path
     *
     * @var string
     */
    protected $imagePath = '';

    /**
     * Fields that will be synced
     *
     * @var array
     */
    protected $syncFields;

    /**
     * If the company can sync stock
     *
     * @var bool
     */
    protected $canSyncStock;

    /**
     * Log message (set after a successful save)
     *
     * @var array|null
     */
    protected $logMessage;

    /**
     * Log context data (set after a successful save)
     *
     * @var array
     */
    protected $logData = [];

    /**
     * Constructor
     *
     * @param array $moloniProduct
     * @param \Product $prestashopProduct Target product (existing or new empty)
     * @param array|null $syncFields
     */
    public function __construct(array $moloniProduct, \Product $prestashopProduct, ?array $syncFields = null)
    {
        $this->moloniProduct = $moloniProduct;
        $this->prestashopProduct = $prestashopProduct;

        $this->syncFields = $syncFields ?? Settings::get('productSyncFields') ?? SyncFields::getDefaultFields();
        $this->canSyncStock = MoloniContext::instance()->company()->canSyncStock();
    }

    //          ABSTRACTS          //

    /**
     * Run the field setters this service needs.
     *
     * @return void
     */
    abstract protected function build(): void;

    /**
     * Actions run after a save.
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    abstract protected function afterSave(): void;

    //          REQUESTS          //

    /**
     * Create product in prestashop
     *
     * @return void
     *
     * @throws MoloniProductException
     * @throws MoloniProductCategoryException
     */
    protected function insert(): void
    {
        $this
            ->setCategories()
            ->setTaxRulesGroupId()
            ->fillPrestaProduct();

        try {
            $this->prestashopProduct->save();

            $this->logMessage = ['Product created in PrestaShop ({0})', ['{0}' => $this->reference]];
            $this->logData = ['moloniProduct' => $this->moloniProduct];

            $this->afterSave();
        } catch (\PrestaShopException $e) {
            throw new MoloniProductException('Error creating product ({0})', ['{0}' => $this->reference], ['message' => $e->getMessage(), 'moloniProduct' => $this->moloniProduct]);
        }
    }

    /**
     * Update product in prestashop
     *
     * @return void
     *
     * @throws MoloniProductException
     * @throws MoloniProductCategoryException
     */
    protected function update(): void
    {
        $this
            ->setCategories()
            ->fillPrestaProduct();

        try {
            $this->prestashopProduct->save();

            $this->logMessage = ['Product updated in PrestaShop ({0})', ['{0}' => $this->reference]];
            $this->logData = ['moloniProduct' => $this->moloniProduct];

            $this->afterSave();
        } catch (\PrestaShopException $e) {
            throw new MoloniProductException('Error updating product ({0})', ['{0}' => $this->reference], ['message' => $e->getMessage(), 'moloniProduct' => $this->moloniProduct]);
        }
    }

    //          PUBLICS          //

    /**
     * Write the save log (opt-in).
     *
     * @return void
     */
    public function saveLog(): void
    {
        if (empty($this->logMessage)) {
            return;
        }

        Logs::addInfoLog($this->logMessage, $this->logData);
    }

    //          GETS          //

    /**
     * Get Moloni product id
     *
     * @return int
     */
    public function getMoloniProductId(): int
    {
        if (empty($this->moloniProduct)) {
            return 0;
        }

        return (int) $this->moloniProduct['productId'];
    }

    /**
     * Get reference
     *
     * @return string
     */
    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * Get PrestaShop product
     *
     * @return \Product
     */
    public function getPrestashopProduct(): \Product
    {
        return $this->prestashopProduct;
    }

    /**
     * Get Prestashop product id
     *
     * @return int
     */
    public function getPrestashopProductId(): int
    {
        return (int) $this->prestashopProduct->id;
    }

    //          SETS          //

    /**
     * Set product visibility
     *
     * @return $this
     */
    public function setVisibility(): self
    {
        if ((int) $this->moloniProduct['visible'] === ProductVisibility::VISIBLE) {
            $this->visibility = 'both';
        } else {
            $this->visibility = 'none';
        }

        return $this;
    }

    /**
     * Set product name
     *
     * @return $this
     */
    public function setName(): self
    {
        $this->name = $this->moloniProduct['name'] ?? '';

        return $this;
    }

    /**
     * Set product reference
     *
     * @return $this
     */
    public function setReference(): self
    {
        $this->reference = $this->moloniProduct['reference'] ?? '';

        return $this;
    }

    /**
     * Set product category
     *
     * @return $this
     *
     * @throws MoloniProductCategoryException
     */
    public function setCategories(): self
    {
        if (!empty($this->categories)) {
            return $this;
        }

        $categoryId = $this->moloniProduct['productCategory']['productCategoryId'] ?? 0;

        if ($categoryId > 0 && $this->shouldSyncCategories()) {
            $this->categories = (new GetPrestashopCategoriesFromMoloniCategoryId($categoryId))->handle();
        }

        return $this;
    }

    /**
     * Set product summary
     *
     * @return $this
     */
    public function setDescription(): self
    {
        $this->description = $this->moloniProduct['summary'] ?? '';
        $this->description = substr($this->description, 0, 800);

        return $this;
    }

    /**
     * Set product identifications
     *
     * @return $this
     */
    public function setIdentifications(): self
    {
        $isbn = '';
        $ean13 = '';
        $upc = '';

        if (!empty($this->moloniProduct['identifications'])) {
            foreach ($this->moloniProduct['identifications'] as $identification) {
                switch ($identification['type']) {
                    case 'ISBN':
                        $isbn = $identification['text'];
                        break;
                    case 'EAN13':
                        $ean13 = $identification['text'];
                        break;
                    case 'UPCA':
                        $upc = $identification['text'];
                        break;
                }
            }
        }

        $this->isbn = $isbn;
        $this->ean13 = $ean13;
        $this->upc = $upc;

        return $this;
    }

    /**
     * Set product price
     *
     * @return $this
     */
    public function setPrice(): self
    {
        $this->price = (float) ($this->moloniProduct['price'] ?? 0);

        return $this;
    }

    /**
     * Set product warehouse
     *
     * @return $this
     */
    public function setWarehouseId(): self
    {
        if (!$this->canSyncStock) {
            return $this;
        }

        $warehouseId = Settings::get('syncStockToPrestashopWarehouse');

        if (empty($warehouseId)) {
            $warehouseId = Warehouse::getCompanyDefaultWarehouse();

            if (empty($warehouseId)) {
                $warehouseId = 1;
            }
        }

        $this->warehouseId = (int) $warehouseId;

        return $this;
    }

    /**
     * Set product has stock
     *
     * @return $this
     */
    public function setHasStock(): self
    {
        if (!$this->canSyncStock) {
            return $this;
        }

        $this->hasStock = $this->moloniProduct['hasStock'] ?? (bool) Boolean::YES;

        return $this;
    }

    /**
     * Set product stock
     *
     * @return $this
     */
    public function setStock(): self
    {
        if (!$this->canSyncStock || !$this->productHasStock()) {
            return $this;
        }

        $this->stock = Stock::getMoloniStock($this->moloniProduct, $this->warehouseId);

        return $this;
    }

    /**
     * Set image path
     *
     * @return $this
     */
    public function setImagePath(): self
    {
        $imagePath = '';

        if (!empty($this->moloniProduct) && !empty($this->moloniProduct['img'])) {
            $imagePath = $this->moloniProduct['img'];
        }

        $this->imagePath = $imagePath;

        return $this;
    }

    /**
     * Sets product taxes
     *
     * @return $this
     */
    public function setTaxRulesGroupId(): self
    {
        if (!empty($this->moloniProduct['taxes']) && !$this->productExists()) {
            $moloniTax = $this->moloniProduct['taxes'][0]['tax'] ?? [];

            $this->taxRulesGroupId = (new FindTaxGroupFromMoloniTax($moloniTax))->handle();
        }

        return $this;
    }

    //          Auxiliary          //

    /**
     * Set prestashop product values
     *
     * @return $this
     */
    protected function fillPrestaProduct(): self
    {
        if ($this->shouldSyncVisibility()) {
            $this->prestashopProduct->visibility = $this->visibility;
        }

        if ($this->shouldSyncName()) {
            $this->prestashopProduct->name = $this->name;
        }

        if ($this->shouldSyncDescription()) {
            $this->prestashopProduct->description_short = $this->description;
        }

        if ($this->shouldSyncPrice()) {
            $this->prestashopProduct->price = $this->price;
        }

        if (empty($this->prestashopProduct->link_rewrite)) {
            $this->prestashopProduct->link_rewrite = $this->linkRewrite();
        }

        if ($this->shouldSyncIdentifiers()) {
            $this->prestashopProduct->ean13 = $this->ean13;
            $this->prestashopProduct->isbn = $this->isbn;
            $this->prestashopProduct->upc = $this->upc;
        }

        if (!$this->productExists()) {
            $this->prestashopProduct->reference = $this->reference;
        }

        $this->prestashopProduct->product_type = $this->type;

        if (!empty($this->categories)) {
            $this->prestashopProduct->id_category_default = $this->categories[0];
        }

        if (!empty($this->taxRulesGroupId)) {
            $this->prestashopProduct->id_tax_rules_group = $this->taxRulesGroupId;
        }

        return $this;
    }

    /**
     * Returns if product already exists
     *
     * @return bool
     */
    protected function productExists(): bool
    {
        return $this->getPrestashopProductId() > 0;
    }

    /**
     * Returns if product has stock
     *
     * @return bool
     */
    protected function productHasStock(): bool
    {
        return $this->hasStock === true;
    }

    /**
     * Cleans link rewrite field
     *
     * @return string
     */
    protected function linkRewrite(): string
    {
        return preg_replace('/[^A-Za-z0-9\-]/', '', $this->name);
    }

    //          VERIFICATIONS          //

    /**
     * Should sync product name
     *
     * @return bool
     */
    protected function shouldSyncName(): bool
    {
        return !$this->productExists() || in_array(SyncFields::NAME, $this->syncFields, true);
    }

    /**
     * Should sync product price
     *
     * @return bool
     */
    protected function shouldSyncPrice(): bool
    {
        return !$this->productExists() || in_array(SyncFields::PRICE, $this->syncFields, true);
    }

    /**
     * Should sync product description
     *
     * @return bool
     */
    protected function shouldSyncDescription(): bool
    {
        return in_array(SyncFields::DESCRIPTION, $this->syncFields, true);
    }

    /**
     * Should sync product categories
     *
     * @return bool
     */
    protected function shouldSyncCategories(): bool
    {
        return !$this->productExists() || in_array(SyncFields::CATEGORIES, $this->syncFields, true);
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

    /**
     * Should sync product image
     *
     * @return bool
     */
    protected function shouldSyncImage(): bool
    {
        return in_array(SyncFields::IMAGE, $this->syncFields, true);
    }

    /**
     * Should sync product visibility
     *
     * @return bool
     */
    protected function shouldSyncVisibility(): bool
    {
        return in_array(SyncFields::VISIBILITY, $this->syncFields, true);
    }
}
