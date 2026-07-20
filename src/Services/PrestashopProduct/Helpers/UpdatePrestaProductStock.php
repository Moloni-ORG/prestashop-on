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

namespace MoloniOn\Services\PrestashopProduct\Helpers;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Sets a PrestaShop product/combination stock quantity.
 *
 * This helper performs the stock write only; logging is handled by the calling
 * service through its saveLog() method, using the result returned by handle().
 */
class UpdatePrestaProductStock
{
    private $prestaProductId;
    private $prestaProductReference;
    private $attributeId;
    private $newStock;

    /**
     * Construct
     *
     * @param int $prestaProductId
     * @param int|null $attributeId
     * @param string $prestaProductReference
     * @param float|int|null $newStock
     */
    public function __construct(int $prestaProductId, ?int $attributeId = null, string $prestaProductReference = '', $newStock = 0)
    {
        $this->prestaProductId = $prestaProductId;
        $this->prestaProductReference = $prestaProductReference;
        $this->attributeId = $attributeId;
        $this->newStock = $newStock;
    }

    /**
     * Handler
     *
     * @return array Log entry: message + data describing the stock change
     */
    public function handle(): array
    {
        $currentStock = (float) \StockAvailable::getQuantityAvailableByProduct($this->prestaProductId, $this->attributeId);
        $data = [];

        if ($this->newStock !== $currentStock) {
            \StockAvailable::setQuantity($this->prestaProductId, $this->attributeId, $this->newStock);

            $message = [
                'Stock updated in PrestaShop (old: {0} | new: {1}) ({2})', [
                    '{0}' => $currentStock,
                    '{1}' => $this->newStock,
                    '{2}' => $this->prestaProductReference,
                ],
            ];
        } else {
            $message = ['Stock is already updated in PrestaShop ({0})', ['{0}' => $this->prestaProductReference]];
            $data = [
                'newStock' => $this->newStock,
                'current' => $currentStock,
                'prestaProductId' => $this->prestaProductId,
                'attributeId' => $this->attributeId,
            ];
        }

        return ['message' => $message, 'data' => $data];
    }
}
