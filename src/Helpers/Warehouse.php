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

namespace MoloniOn\Helpers;

use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Tools\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Warehouse
{
    /**
     * Resolve the warehouse to sync stock TO Moloni (PrestaShop -> Moloni).
     *
     * Uses the configured warehouse; when unset (0/1) falls back to the company
     * default and requires one to exist.
     *
     * @return int
     *
     * @throws MoloniProductException
     */
    public static function resolveMoloniStockWarehouse(): int
    {
        $warehouseId = (int) Settings::get('syncStockToMoloniWarehouse');

        if (in_array($warehouseId, [0, 1])) {
            $warehouseId = self::getCompanyDefaultWarehouse();

            if (empty($warehouseId)) {
                throw new MoloniProductException('Company does not have a default warehouse, please select one');
            }
        }

        return $warehouseId;
    }

    /**
     * Resolve the warehouse to read stock FROM Moloni (Moloni -> PrestaShop).
     *
     * Uses the configured warehouse; when unset falls back to the company
     * default, then to warehouse 1.
     *
     * @return int
     */
    public static function resolvePrestashopStockWarehouse(): int
    {
        $warehouseId = Settings::get('syncStockToPrestashopWarehouse');

        if (empty($warehouseId)) {
            $warehouseId = self::getCompanyDefaultWarehouse();

            if (empty($warehouseId)) {
                $warehouseId = 1;
            }
        }

        return (int) $warehouseId;
    }

    public static function getCompanyDefaultWarehouse(): int
    {
        $params = [
            'options' => [
                'filter' => [
                    'field' => 'isDefault',
                    'comparison' => 'eq',
                    'value' => '1',
                ],
            ],
        ];

        try {
            $query = MoloniApiClient::warehouses()->queryWarehouses($params);

            if (!empty($query)) {
                return (int) $query[0]['warehouseId'];
            }
        } catch (MoloniApiException $e) {
        }

        return 0;
    }
}
