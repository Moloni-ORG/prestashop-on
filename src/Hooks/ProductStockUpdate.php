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

declare(strict_types=1);

namespace MoloniOn\Hooks;

use MoloniOn\Api\MoloniApi;
use MoloniOn\Enums\Boolean;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\MoloniContext;
use MoloniOn\Services\MoloniProduct\Helpers\FindMoloniProductByReference;
use MoloniOn\Services\MoloniProduct\Stock\SyncProductStock;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\Settings;
use MoloniOn\Tools\SyncLogs;
use Product;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductStockUpdate extends AbstractHookAction
{
    private $productId;
    private $variantId;
    private $newQty;

    public function __construct(int $productId, int $variantId, float $newQty)
    {
        $this->productId = $productId;
        $this->variantId = $variantId;
        $this->newQty = $newQty;

        $this->handle();
    }

    private function handle(): void
    {
        if (!$this->shouldExecuteHandle()) {
            return;
        }

        try {
            SyncLogs::prestashopProductAddTimeout($this->productId);
            $product = new \Product($this->productId, true, \Configuration::get('PS_LANG_DEFAULT'));

            $isVariant = $product->product_type === 'combinations' && $product->hasCombinations();

            if ($isVariant && !$this->variantId) {
                return;
            }

            $moloniProduct = FindMoloniProductByReference::fromPrestashopProduct($product);

            if (empty($moloniProduct)) {
                return;
            }

            $service = new SyncProductStock(
                $product,
                $moloniProduct,
                $isVariant ? $this->variantId : 0,
                $this->newQty
            );

            $service->run();
            $service->saveLog();
        } catch (MoloniProductException $e) {
            Logs::addErrorLog(
                [['Error saving Moloni ON product'], [$e->getMessage(), $e->getIdentifiers()]],
                $e->getData()
            );
        }
    }

    /**
     * Let this conditions be the same to allow for updates or inserts if we are inserting or updating a product
     */
    private function shouldExecuteHandle(): bool
    {
        if ($this->productId < 1) {
            return false;
        }

        if ((int) Settings::get('addProductsToMoloni') === Boolean::NO
            && (int) Settings::get('updateProductsToMoloni') === Boolean::NO) {
            return false;
        }

        if ((int) Settings::get('syncStockToMoloni') === Boolean::NO) {
            return false;
        }

        if (SyncLogs::prestashopProductHasTimeout($this->productId)) {
            return false;
        }

        if (!MoloniApi::hasAuthenticationAndCompany()) {
            return false;
        }

        return MoloniContext::instance()->company()->canSyncStock();
    }
}
