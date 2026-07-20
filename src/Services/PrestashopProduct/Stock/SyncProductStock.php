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

namespace MoloniOn\Services\PrestashopProduct\Stock;

use MoloniOn\Enums\Boolean;
use MoloniOn\Helpers\Stock;
use MoloniOn\Helpers\Warehouse;
use MoloniOn\MoloniContext;
use MoloniOn\Services\PrestashopProduct\Helpers\UpdatePrestaProductStock;
use MoloniOn\Services\PrestashopProduct\Interfaces\PrestashopProductServiceInterface;
use MoloniOn\Services\PrestashopProduct\ProductCombination;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Aligns a PrestaShop product's stock with Moloni.
 *
 * Replaces the type-specific updateStock() methods of the old builders with a
 * single service: the caller no longer branches on simple vs. combinations.
 */
class SyncProductStock implements PrestashopProductServiceInterface
{
    /**
     * Moloni product (source)
     *
     * @var array
     */
    private $moloniProduct;

    /**
     * PrestaShop product (target)
     *
     * @var \Product
     */
    private $prestashopProduct;

    /**
     * If the company can sync stock
     *
     * @var bool
     */
    private $canSyncStock;

    /**
     * Resolved warehouse id
     *
     * @var int
     */
    private $warehouseId = 0;

    /**
     * Stock log entries, consumed by saveLog()
     *
     * @var array
     */
    private $results = [];

    /**
     * Constructor
     *
     * @param array $moloniProduct
     * @param \Product $prestashopProduct
     */
    public function __construct(array $moloniProduct, \Product $prestashopProduct)
    {
        $this->moloniProduct = $moloniProduct;
        $this->prestashopProduct = $prestashopProduct;
        $this->canSyncStock = MoloniContext::instance()->company()->canSyncStock();
    }

    //          PUBLICS          //

    /**
     * Runner
     *
     * @return void
     */
    public function run(): void
    {
        if (!$this->canSyncStock) {
            return;
        }

        if ((int) $this->prestashopProduct->id <= 0) {
            return;
        }

        if (!($this->moloniProduct['hasStock'] ?? true)) {
            return;
        }

        $this->warehouseId = $this->resolveWarehouseId();

        if (!empty($this->moloniProduct['variants'])) {
            $this->syncCombinations();
        } else {
            $this->syncSimple();
        }
    }

    /**
     * Write stock logs (opt-in)
     *
     * @return void
     */
    public function saveLog(): void
    {
        foreach ($this->results as $result) {
            Logs::addStockLog($result['message'], $result['data']);
        }
    }

    //          PRIVATES          //

    /**
     * Sync a simple product's stock
     *
     * @return void
     */
    private function syncSimple(): void
    {
        $stock = Stock::getMoloniStock($this->moloniProduct, $this->warehouseId);

        $this->results[] = (new UpdatePrestaProductStock(
            (int) $this->prestashopProduct->id,
            null,
            $this->moloniProduct['reference'] ?? '',
            $stock
        ))->handle();
    }

    /**
     * Sync a product-with-combinations stock
     *
     * @return void
     */
    private function syncCombinations(): void
    {
        foreach ($this->moloniProduct['variants'] as $variant) {
            if ($variant['visible'] !== Boolean::YES) {
                continue;
            }

            $combination = new ProductCombination($this->prestashopProduct, $this->moloniProduct, $variant);

            $result = $combination->updateStock();

            if ($result !== null) {
                $this->results[] = $result;
            }
        }
    }

    /**
     * Resolve the warehouse to read stock from
     *
     * @return int
     */
    private function resolveWarehouseId(): int
    {
        $warehouseId = Settings::get('syncStockToPrestashopWarehouse');

        if (empty($warehouseId)) {
            $warehouseId = Warehouse::getCompanyDefaultWarehouse();

            if (empty($warehouseId)) {
                $warehouseId = 1;
            }
        }

        return (int) $warehouseId;
    }
}
