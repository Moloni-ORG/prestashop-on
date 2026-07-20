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

namespace MoloniOn\Actions\Imports;

use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Services\PrestashopProduct\Helpers\FindPrestashopProductByReference;
use MoloniOn\Services\PrestashopProduct\Stock\SyncProductStock;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\SyncLogs;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ImportStockChangesFromMoloni extends ImportProducts
{
    /**
     * @throws MoloniApiException
     */
    public function handle(): void
    {
        $props = [
            'options' => [
                'order' => [
                    'field' => 'reference',
                    'sort' => 'DESC',
                ],
                'filter' => [
                    'field' => 'hasStock',
                    'comparison' => 'eq',
                    'value' => '1',
                ],
                'pagination' => [
                    'page' => $this->page,
                    'qty' => $this->itemsPerPage,
                ],
            ],
        ];

        try {
            $query = MoloniApiClient::products()->queryProducts($props, true);
        } catch (MoloniApiException $e) {
            Logs::addErrorLog(['Error importing products stock. Part {0}', ['{0}' => $this->page]], $e->getData());

            throw $e;
        }

        $this->totalResults = (int) ($query['data']['products']['options']['pagination']['count'] ?? 0);

        $data = $query['data']['products']['data'] ?? [];

        foreach ($data as $product) {
            SyncLogs::moloniProductAddTimeout((int) $product['productId']);

            try {
                $prestashopProduct = FindPrestashopProductByReference::fromMoloniProduct($product);

                if ((int) $prestashopProduct->id > 0) {
                    SyncLogs::prestashopProductAddTimeout((int) $prestashopProduct->id);

                    // Bulk import: skip saveLog() to avoid flooding the logs
                    $service = new SyncProductStock($product, $prestashopProduct);
                    $service->run();

                    $this->syncedProducts[] = $product['reference'];
                } else {
                    $this->errorProducts[] = [
                        $product['reference'] => 'Product does not exist in PrestaShop',
                    ];
                }
            } catch (MoloniProductException $e) {
                $this->errorProducts[] = [
                    $product['reference'] => $e->getData(),
                ];
            }
        }

        $logMsg = ['Products stock import. Part {0}', ['{0}' => $this->page]];
        $logData = [
            'success' => $this->syncedProducts,
            'error' => $this->errorProducts,
        ];
        Logs::addInfoLog($logMsg, $logData);
    }
}
