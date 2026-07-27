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

namespace MoloniOn\Actions\Exports;

use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\MoloniContext;
use MoloniOn\Services\MoloniProduct\Create\CreateSimpleProduct;
use MoloniOn\Services\MoloniProduct\Create\CreateVariantProduct;
use MoloniOn\Services\MoloniProduct\Helpers\FindMoloniProductByReference;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\SyncLogs;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ExportProductsToMoloni extends ExportProducts
{
    public function handle(): void
    {
        $start = ($this->page - 1) * $this->itemsPerPage;

        $products = \Product::getProducts(
            $this->languageId,
            $start,
            $this->itemsPerPage,
            'id_product',
            'DESC',
            false,
            true
        );

        $this->totalResults = count($products);

        $hasProperties = MoloniContext::instance()->company()->hasProperties();
        $skippedVariants = [];

        foreach ($products as $productData) {
            if (empty($productData['reference'])) {
                $this->errorProducts[] = [
                    $productData['id_product'] => 'Product has no reference in PrestaShop.',
                ];

                continue;
            }

            SyncLogs::prestashopProductAddTimeout((int) $productData['id_product']);

            $product = new \Product($productData['id_product'], true, $this->languageId);

            try {
                $isVariant = $product->product_type === 'combinations' && $product->hasCombinations();

                if ($isVariant && !$hasProperties) {
                    $skippedVariants[] = $product->reference;

                    continue;
                }

                $moloniProduct = FindMoloniProductByReference::fromPrestashopProduct($product);

                if (empty($moloniProduct)) {
                    // Bulk export: skip saveLog() to avoid flooding the logs
                    $service = $isVariant
                        ? new CreateVariantProduct($product)
                        : new CreateSimpleProduct($product);

                    $service->run();

                    $this->syncedProducts[] = $product->reference;
                } else {
                    $this->errorProducts[] = [
                        $product->reference => 'Product already exists in Moloni ON',
                    ];
                }
            } catch (MoloniProductException $e) {
                $this->errorProducts[] = [
                    $product->reference => $e->getData(),
                ];
            }
        }

        if (!empty($skippedVariants)) {
            Logs::addWarningLog(
                'Products with combinations were skipped: the Product Properties module is not active in your Moloni ON company.',
                ['module' => 'productsServices.productProperties', 'references' => $skippedVariants]
            );
        }

        $logMsg = ['Products export. Part {0}', ['{0}' => $this->page]];
        $logData = [
            'success' => $this->syncedProducts,
            'error' => $this->errorProducts,
        ];

        Logs::addInfoLog($logMsg, $logData);
    }
}
