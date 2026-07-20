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

namespace MoloniOn\Services\MoloniProduct\Stock;

use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Helpers\Warehouse;
use MoloniOn\MoloniContext;
use MoloniOn\Services\MoloniProduct\Helpers\UpdateMoloniProductStock;
use MoloniOn\Services\MoloniProduct\Helpers\Variants\FindVariant;
use MoloniOn\Services\MoloniProduct\Interfaces\MoloniProductServiceInterface;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Aligns a Moloni product's stock with PrestaShop.
 *
 * Replaces the type-specific updateStock() methods of the old builders with a
 * single service: the caller no longer branches on simple vs. variant. When a
 * combination id and quantity are given (stock hook) only that variant is
 * synced; otherwise every managed line is synced from PrestaShop's stock.
 */
class SyncProductStock implements MoloniProductServiceInterface
{
    /**
     * PrestaShop product object
     *
     * @var \Product
     */
    private $prestashopProduct;

    /**
     * Fetched Moloni product
     *
     * @var array
     */
    private $moloniProduct;

    /**
     * Target PrestaShop combination id (0 = all)
     *
     * @var int
     */
    private $variantId;

    /**
     * New stock quantity for the target combination/product (null = read from PrestaShop)
     *
     * @var float|null
     */
    private $newQty;

    /**
     * Resolved warehouse id
     *
     * @var int
     */
    private $warehouseId = 0;

    /**
     * Movement results, consumed by saveLog()
     *
     * @var array
     */
    private $results = [];

    /**
     * Constructor
     *
     * @param \Product $prestashopProduct
     * @param array $moloniProduct Already fetched Moloni product
     * @param int|null $variantId Target PrestaShop combination id (0/null = all)
     * @param float|null $newQty New quantity for the target (null = read from PrestaShop)
     */
    public function __construct(
        \Product $prestashopProduct,
        array $moloniProduct,
        ?int $variantId = 0,
        ?float $newQty = null
    ) {
        $this->prestashopProduct = $prestashopProduct;
        $this->moloniProduct = $moloniProduct;
        $this->variantId = (int) $variantId;
        $this->newQty = $newQty;
    }

    //          PUBLICS          //

    /**
     * Runner
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    public function run(): void
    {
        if ($this->getMoloniProductId() <= 0) {
            return;
        }

        if (!MoloniContext::instance()->company()->canSyncStock()) {
            return;
        }

        $this->warehouseId = $this->resolveWarehouseId();

        if (!empty($this->moloniProduct['variants'])) {
            $this->syncVariants();
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
            if (!$result['moved']) {
                Logs::addStockLog(
                    ['Stock is already updated in Moloni ON ({0})', ['{0}' => $result['reference']]],
                    ['newStock' => $result['newStock'], 'current' => $result['oldStock']]
                );

                continue;
            }

            if ($result['success']) {
                Logs::addStockLog(
                    [
                        'Stock updated in Moloni ON (old: {0} | new: {1}) ({2})',
                        [
                            '{0}' => $result['oldStock'],
                            '{1}' => $result['newStock'],
                            '{2}' => $result['reference'],
                        ],
                    ],
                    ['mutation' => $result['mutation']]
                );
            } else {
                Logs::addStockLog(
                    ['Something went wrong updating stock ({0})', ['{0}' => $result['reference']]],
                    ['mutation' => $result['mutation']]
                );
            }
        }
    }

    //          PRIVATES          //

    /**
     * Sync a simple product's stock
     *
     * @throws MoloniProductException
     */
    private function syncSimple(): void
    {
        if (!($this->moloniProduct['hasStock'] ?? true)) {
            return;
        }

        $stock = $this->newQty ?? (float) \StockAvailable::getQuantityAvailableByProduct($this->prestashopProduct->id);

        $this->createMovement(
            $this->getMoloniProductId(),
            (float) $stock,
            $this->moloniProduct['warehouses'] ?? [],
            (string) ($this->moloniProduct['reference'] ?? '')
        );
    }

    /**
     * Sync a product-with-variants stock
     *
     * @throws MoloniProductException
     */
    private function syncVariants(): void
    {
        if (!($this->moloniProduct['hasStock'] ?? true)) {
            return;
        }

        $languageId = (int) \Configuration::get('PS_LANG_DEFAULT');
        $prestashopCombinationsQuery = $this->prestashopProduct->getAttributeCombinations(null, false);

        foreach ($prestashopCombinationsQuery as $combinationQuery) {
            $combinationId = (int) $combinationQuery['id_product_attribute'];

            if ($this->variantId > 0 && $combinationId !== $this->variantId) {
                continue;
            }

            $combination = new \Combination($combinationId, $languageId);

            $variant = (new FindVariant(
                $combinationId,
                (string) $combination->reference,
                $this->moloniProduct['variants'],
                []
            ))->handle();

            if (empty($variant)) {
                continue;
            }

            if ($this->variantId > 0 && $this->newQty !== null) {
                $stock = $this->newQty;
            } else {
                $stock = (float) \StockAvailable::getQuantityAvailableByProduct(
                    $this->prestashopProduct->id,
                    $combinationId
                );
            }

            $this->createMovement(
                (int) $variant['productId'],
                (float) $stock,
                $variant['warehouses'] ?? [],
                (string) ($variant['reference'] ?? $combination->reference)
            );
        }
    }

    /**
     * Create a single stock movement and record its result
     *
     * @throws MoloniProductException
     */
    private function createMovement(int $moloniProductId, float $stock, array $warehouses, string $reference): void
    {
        try {
            $this->results[] = (new UpdateMoloniProductStock(
                $moloniProductId,
                $this->warehouseId,
                $stock,
                $warehouses,
                $reference
            ))->handle();
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Error creating stock movement ({0})', ['{0}' => $reference], $e->getData());
        }
    }

    /**
     * Resolve the warehouse to sync stock to
     *
     * @throws MoloniProductException
     */
    private function resolveWarehouseId(): int
    {
        $warehouseId = (int) Settings::get('syncStockToMoloniWarehouse');

        if (in_array($warehouseId, [0, 1])) {
            $warehouseId = Warehouse::getCompanyDefaultWarehouse();

            if (empty($warehouseId)) {
                throw new MoloniProductException('Company does not have a default warehouse, please select one');
            }
        }

        return $warehouseId;
    }

    /**
     * Moloni product id getter
     *
     * @return int
     */
    private function getMoloniProductId(): int
    {
        if (empty($this->moloniProduct)) {
            return 0;
        }

        return (int) $this->moloniProduct['productId'];
    }
}
