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

namespace MoloniOn\Services\MoloniProduct\Helpers;

use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Exceptions\MoloniApiException;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Creates the stock movement (entry/exit) needed to align a Moloni product's
 * stock with the given quantity.
 *
 * This helper performs the mutation only; logging is handled by the calling
 * service through its saveLog() method, using the result returned by handle().
 */
class UpdateMoloniProductStock
{
    private $moloniProductId;
    private $moloniProductWarehouses;
    private $reference;
    private $warehouseId;
    private $newStock;

    /**
     * Construct
     *
     * @param int $moloniProductId
     * @param int $warehouseId
     * @param float $newStock
     * @param array $moloniProductWarehouses
     * @param string $reference
     */
    public function __construct(
        int $moloniProductId,
        int $warehouseId,
        float $newStock,
        array $moloniProductWarehouses,
        string $reference
    ) {
        $this->moloniProductId = $moloniProductId;
        $this->warehouseId = $warehouseId;
        $this->newStock = $newStock;

        $this->moloniProductWarehouses = $moloniProductWarehouses;
        $this->reference = $reference;
    }

    /**
     * Handler
     *
     * @return array Movement result: reference, oldStock, newStock, moved, success, mutation
     *
     * @throws MoloniApiException
     */
    public function handle(): array
    {
        $moloniStock = 0;

        foreach ($this->moloniProductWarehouses as $warehouse) {
            if ($warehouse['warehouseId'] === $this->warehouseId) {
                $moloniStock = $warehouse['stock'];
                break;
            }
        }

        $result = [
            'reference' => $this->reference,
            'oldStock' => (float) $moloniStock,
            'newStock' => $this->newStock,
            'moved' => false,
            'success' => true,
            'mutation' => null,
        ];

        if ((float) $moloniStock === $this->newStock) {
            return $result;
        }

        $props = [
            'productId' => $this->moloniProductId,
            'notes' => 'Prestashop',
            'warehouseId' => $this->warehouseId,
        ];

        if ($moloniStock > $this->newStock) {
            $props['qty'] = $moloniStock - $this->newStock;

            $mutation = MoloniApiClient::stock()->mutationStockMovementManualExitCreate(['data' => $props]);
        } else {
            $props['qty'] = $this->newStock - $moloniStock;

            $mutation = MoloniApiClient::stock()->mutationStockMovementManualEntryCreate(['data' => $props]);
        }

        $result['moved'] = true;
        $result['mutation'] = $mutation;
        $result['success'] = isset($mutation['data']['stockMovementManualEntryCreate']['data']['stockMovementId'])
            || isset($mutation['data']['stockMovementManualExitCreate']['data']['stockMovementId']);

        return $result;
    }
}
