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
use MoloniOn\Enums\Countries;
use MoloniOn\Enums\ProductType;
use MoloniOn\Enums\ProductTypeAT;
use MoloniOn\Enums\ProductVisibility;
use MoloniOn\Enums\SyncFields;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\MoloniException;
use MoloniOn\Exceptions\Product\MoloniProductCategoryException;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Exceptions\Product\MoloniProductTaxException;
use MoloniOn\Helpers\Warehouse;
use MoloniOn\MoloniContext;
use MoloniOn\Services\MoloniProduct\Interfaces\MoloniProductServiceInterface;
use MoloniOn\Services\MoloniProduct\ProductCategory;
use MoloniOn\Services\Tax\TaxFromRate;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Shared logic for the PrestaShop -> Moloni product sync services.
 *
 * Holds every field setter, the create/update mutation calls and the logging,
 * which used to be duplicated between the simple and variant builders.
 * Concrete services compose the setters they need in build() and pick between
 * insert() and update().
 */
abstract class MoloniProductSyncAbstract implements MoloniProductServiceInterface
{
    /**
     * PrestaShop product object
     *
     * @var \Product
     */
    protected $prestashopProduct;

    /**
     * Moloni product (fetched, then mutated)
     *
     * @var array
     */
    protected $moloniProduct = [];

    /**
     * Visibility
     *
     * @var int
     */
    protected $visibility;

    /**
     * Category
     *
     * @var ProductCategory|null
     */
    protected $category;

    /**
     * Moloni product type
     *
     * @var int
     */
    protected $type;

    /**
     * Moloni AT product type
     *
     * @var string
     */
    protected $typeAT = '';

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
    protected $summary;

    /**
     * Product identifications
     *
     * @var array
     */
    protected $identifications = [];

    /**
     * Product cover image
     *
     * @var array
     */
    protected $coverImage = [];

    /**
     * Product price
     *
     * @var float
     */
    protected $price;

    /**
     * Has stock
     *
     * @var bool
     */
    protected $hasStock = false;

    /**
     * Stock quantity
     *
     * @var float
     */
    protected $stock = 0;

    /**
     * Measurement unit
     *
     * @var int
     */
    protected $measurementUnitId;

    /**
     * Warehouse
     *
     * @var int
     */
    protected $warehouseId = 0;

    /**
     * Tax builder
     *
     * @var TaxFromRate|null
     */
    protected $tax;

    /**
     * Eco tax
     *
     * @var float
     */
    protected $ecoTax;

    /**
     * Product Exemption reason
     *
     * @var string
     */
    protected $exemptionReason = '';

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
     * @param \Product $prestashopProduct
     * @param array $moloniProduct Already fetched Moloni product (empty for a create)
     */
    public function __construct(\Product $prestashopProduct, array $moloniProduct = [])
    {
        $this->prestashopProduct = $prestashopProduct;
        $this->moloniProduct = $moloniProduct;

        $this->syncFields = Settings::get('productSyncFields') ?? SyncFields::getDefaultFields();
        $this->canSyncStock = MoloniContext::instance()->company()->canSyncStock();
    }

    //          ABSTRACTS          //

    /**
     * Assemble the payload sent to Moloni.
     *
     * @return array
     */
    abstract protected function toArray(): array;

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
     */
    abstract protected function afterSave(): void;

    /**
     * Actions run before an update.
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    abstract protected function beforeUpdate(): void;

    //          REQUESTS          //

    /**
     * Create a product in Moloni.
     *
     * @throws MoloniProductException
     */
    protected function insert(): void
    {
        $this->setCategory();

        $props = $this->toArray();

        try {
            $mutation = MoloniApiClient::products()
                ->mutationProductCreate(['data' => $props]);

            $moloniProduct = $mutation['data']['productCreate']['data'] ?? [];

            if (empty($moloniProduct)) {
                throw new MoloniProductException('Error creating product ({0})', ['{0}' => $this->reference], ['mutation' => $mutation, 'props' => $props]);
            }

            $this->moloniProduct = $moloniProduct;

            $this->logMessage = ['Product created in Moloni ON ({0})', ['{0}' => $this->reference]];
            $this->logData = ['props' => $props];

            $this->afterSave();
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Error creating product ({0})', ['{0}' => $this->reference], $e->getData());
        }
    }

    /**
     * Update a product in Moloni.
     *
     * @throws MoloniProductException
     */
    protected function update(): void
    {
        $this->setCategory();
        $this->beforeUpdate();

        $props = $this->toArray();

        try {
            $mutation = MoloniApiClient::products()
                ->mutationProductUpdate(['data' => $props]);

            $moloniProduct = $mutation['data']['productUpdate']['data'] ?? [];
            $productId = $moloniProduct['productId'] ?? 0;

            if ($productId <= 0) {
                throw new MoloniProductException('Error updating product ({0})', ['{0}' => $this->reference], ['mutation' => $mutation, 'props' => $props]);
            }

            $this->moloniProduct = $moloniProduct;

            $this->logMessage = ['Product updated in Moloni ON ({0})', ['{0}' => $this->reference]];
            $this->logData = ['props' => $props];

            $this->afterSave();
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Error updating product ({0})', ['{0}' => $this->reference], $e->getData());
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
     * Moloni product id getter
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
     * Moloni product getter
     *
     * @return array
     */
    public function getMoloniProduct(): array
    {
        return $this->moloniProduct;
    }

    //          SETS          //

    /**
     * Set product visibility
     *
     * @return $this
     */
    public function setVisibility(): self
    {
        if ($this->prestashopProduct->visibility === 'none') {
            $this->visibility = ProductVisibility::HIDDEN;
        } else {
            $this->visibility = ProductVisibility::VISIBLE;
        }

        return $this;
    }

    /**
     * Set product reference
     *
     * @return $this
     */
    public function setReference(): self
    {
        $reference = $this->prestashopProduct->reference;

        if (empty($reference)) {
            $reference = (string) $this->prestashopProduct->id;
        }

        $this->reference = $reference;

        return $this;
    }

    /**
     * Set product name
     *
     * @return $this
     */
    public function setName(): self
    {
        $this->name = $this->prestashopProduct->name;

        return $this;
    }

    /**
     * Set product summary
     *
     * @return $this
     */
    public function setSummary(): self
    {
        $this->summary = strip_tags($this->prestashopProduct->description_short);

        return $this;
    }

    /**
     * Set product price
     *
     * @return $this
     */
    public function setPrice(): self
    {
        $this->price = $this->prestashopProduct->getPriceWithoutReduct(true, null, 5);

        return $this;
    }

    /**
     * Set product type
     *
     * @return $this
     */
    public function setType(): self
    {
        $this->type = ProductType::PRODUCT;

        return $this;
    }

    /**
     * Set AT product type
     *
     * @return $this
     */
    public function setTypeAT(): self
    {
        $this->typeAT = $this->moloniProduct['productAT']['productType'] ?? ProductTypeAT::GOODS;

        return $this;
    }

    /**
     * Set has stock
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
     * Set stock quantity
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

        $this->stock = \StockAvailable::getQuantityAvailableByProduct($this->prestashopProduct->id);

        return $this;
    }

    /**
     * Sets product tax
     *
     * @return $this
     *
     * @throws MoloniProductTaxException
     */
    public function setTax(): self
    {
        try {
            $company = MoloniContext::instance()->company()->getAll();

            $address = new \Address();
            $address->id_country = \Country::getByIso($company['fiscalZone']['fiscalZone'] ?? 'PT');

            $taxRate = (float) $this->prestashopProduct->getTaxesRate($address);

            if ($taxRate > 0) {
                $fiscalZone = [
                    'code' => $company['fiscalZone']['fiscalZone'] ?? 'PT',
                    'countryId' => $company['country']['countryId'] ?? Countries::PORTUGAL,
                ];

                $taxBuilder = new TaxFromRate($taxRate, $fiscalZone, 1);
                $taxBuilder->getOrCreate();

                $this->tax = $taxBuilder;
            } else {
                $this->exemptionReason = Settings::get('exemptionReasonProduct');
            }
        } catch (MoloniApiException $e) {
            throw new MoloniProductTaxException('Error fetching company data', [], $e->getData());
        } catch (MoloniException $e) {
            throw new MoloniProductTaxException('Error creating tax', [], $e->getData());
        }

        return $this;
    }

    /**
     * Sets product eco-tax
     *
     * @return $this
     */
    public function setEcoTax(): self
    {
        $ecoTax = (float) $this->prestashopProduct->ecotax;

        if ($ecoTax > 0) {
            $this->price -= $ecoTax;
            // todo: what else is needed?
        }

        $this->ecoTax = $ecoTax;

        return $this;
    }

    /**
     * Set product warehouse
     *
     * @return $this
     *
     * @throws MoloniProductException
     */
    public function setWarehouseId(): self
    {
        if (!$this->canSyncStock) {
            return $this;
        }

        $warehouseId = (int) Settings::get('syncStockToMoloniWarehouse');

        if (in_array($warehouseId, [0, 1])) {
            $warehouseId = Warehouse::getCompanyDefaultWarehouse();

            if (empty($warehouseId)) {
                throw new MoloniProductException('Company does not have a default warehouse, please select one');
            }
        }

        $this->warehouseId = $warehouseId;

        return $this;
    }

    /**
     * Set product category
     *
     * @return $this
     *
     * @throws MoloniProductCategoryException
     */
    public function setCategory(): self
    {
        if (!empty($this->category)) {
            return $this;
        }

        if (!$this->shouldSyncCategories()) {
            return $this;
        }

        $categoriesNames = [];

        if (!empty($this->prestashopProduct->id_category_default)) {
            $languageId = (int) \Configuration::get('PS_LANG_DEFAULT');
            $rootCategoryId = (int) \Category::getRootCategory()->id;

            $categoryId = $this->prestashopProduct->id_category_default;
            $failsafe = 0;

            do {
                $categoryObj = new \Category($categoryId, $languageId);

                // For some reason sometimes this comes empty
                if (empty($categoryObj->name)) {
                    break;
                }

                array_unshift($categoriesNames, $categoryObj->name);

                // Skip root category
                if ((int) $categoryObj->id_parent === $rootCategoryId) {
                    break;
                }

                // Next category is this category parent
                $categoryId = (int) $categoryObj->id_parent;

                ++$failsafe;
            } while ($failsafe < 100 && $categoryId > 0);
        }

        if (empty($categoriesNames)) {
            $categoriesNames = ['Prestashop'];
        }

        try {
            $parentId = 0;

            foreach ($categoriesNames as $category) {
                $builder = new ProductCategory($category, $parentId);

                $builder->search();

                if ($builder->getProductCategoryId() === 0) {
                    $builder->insert();
                }

                $parentId = $builder->getProductCategoryId();
            }

            /* @noinspection PhpUndefinedVariableInspection */
            $this->category = $builder;
        } catch (MoloniException $e) {
            throw new MoloniProductCategoryException($e->getMessage(), $e->getIdentifiers(), $e->getData());
        }

        return $this;
    }

    /**
     * Set product measurement unit
     *
     * @return $this
     */
    public function setMeasurementUnitId(): self
    {
        $this->measurementUnitId = (int) (Settings::get('measurementUnit') ?? 0);

        return $this;
    }

    /**
     * Set product image
     *
     * @return $this
     */
    public function setCoverImage(): self
    {
        /** @var array|null $coverImage */
        $coverImage = \Image::getCover($this->prestashopProduct->id);

        $this->coverImage = $coverImage ?? [];

        return $this;
    }

    /**
     * Set product identifications
     *
     * @return $this
     */
    public function setIdentifications(): self
    {
        $identifications = [];

        $isEanFav = false;
        $isIsbnFav = false;
        $isUpcaFav = false;

        if (isset($this->moloniProduct['identifications']) && !empty($this->moloniProduct['identifications'])) {
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

        if (!empty($this->prestashopProduct->ean13)) {
            $identifications[] = [
                'type' => 'EAN13',
                'text' => $this->prestashopProduct->ean13,
                'favorite' => $isEanFav,
            ];
        }

        if (!empty($this->prestashopProduct->isbn)) {
            $identifications[] = [
                'type' => 'ISBN',
                'text' => $this->prestashopProduct->isbn,
                'favorite' => $isIsbnFav,
            ];
        }

        if (!empty($this->prestashopProduct->upc)) {
            $identifications[] = [
                'type' => 'UPCA',
                'text' => $this->prestashopProduct->upc,
                'favorite' => $isUpcaFav,
            ];
        }

        $this->identifications = $identifications;

        return $this;
    }

    //          Auxiliary          //

    /**
     * Check if current product exists
     *
     * @return bool
     */
    protected function productExists(): bool
    {
        return $this->getMoloniProductId() > 0;
    }

    /**
     * Checks if product has stock
     *
     * @return bool
     */
    protected function productHasStock(): bool
    {
        return $this->hasStock;
    }

    //          VERIFICATIONS          //

    /**
     * Verify requirements to create product
     *
     * @return $this
     *
     * @throws MoloniProductException
     */
    protected function verifyPrestaProduct(): self
    {
        if (empty($this->prestashopProduct->id)) {
            throw new MoloniProductException('PrestaShop product not found');
        }

        return $this;
    }

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
        if (!$this->productExists()) {
            return true;
        }

        return in_array(SyncFields::CATEGORIES, $this->syncFields, true);
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
