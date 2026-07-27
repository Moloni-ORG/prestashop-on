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

namespace MoloniOn\Webservice\Product;

use MoloniOn\Enums\Boolean;
use MoloniOn\Enums\StockSync;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Services\PrestashopProduct\Create\CreateCombinationsProduct;
use MoloniOn\Services\PrestashopProduct\Create\CreateSimpleProduct;
use MoloniOn\Services\PrestashopProduct\Helpers\FindPrestashopProductByReference;
use MoloniOn\Services\PrestashopProduct\Stock\SyncProductStock;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\Settings;
use MoloniOn\Tools\SyncLogs;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductCreate extends AbstractWebserviceAction
{
    public function handle(): void
    {
        if (!$this->shouldExecuteHandle()) {
            return;
        }

        try {
            $product = $this->fetchProductFromMoloni($this->productId);

            // Should not sync child product by itself
            if (!empty($product['parent'])) {
                return;
            }

            // Should not sync ignored references
            if (StockSync::isIgnoredReference($product['reference'])) {
                return;
            }

            $isCombinations = !empty($product['variants']);

            $prestashopProduct = FindPrestashopProductByReference::fromMoloniProduct($product);

            if ((int) $prestashopProduct->id === 0) {
                SyncLogs::moloniProductAddTimeout($this->productId);

                $service = $isCombinations
                    ? new CreateCombinationsProduct($product, $prestashopProduct)
                    : new CreateSimpleProduct($product, $prestashopProduct);
                $service->run();
                $service->saveLog();

                SyncLogs::prestashopProductAddTimeout($service->getPrestashopProductId());

                $stockService = new SyncProductStock($product, $service->getPrestashopProduct());
                $stockService->run();
                $stockService->saveLog();
            }
        } catch (MoloniProductException $e) {
            Logs::addErrorLog([['Error saving PrestaShop product'], [$e->getMessage(), $e->getIdentifiers()]], $e->getData());
        }
    }

    private function shouldExecuteHandle(): bool
    {
        if ($this->productId < 1) {
            return false;
        }

        if ((int) Settings::get('addProductsToPrestashop') === Boolean::NO) {
            return false;
        }

        if (SyncLogs::moloniProductHasTimeout($this->productId)) {
            return false;
        }

        return $this->isAuthenticated();
    }
}
