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

namespace MoloniOn\Services\MoloniProduct\Helpers;

use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\Product\MoloniProductException;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Fetches a single Moloni product by its id.
 */
class FindMoloniProductById
{
    /**
     * Run the search.
     *
     * @param int $productId
     *
     * @return array Matched Moloni product, or empty array when not found
     *
     * @throws MoloniProductException
     */
    public static function handle(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        try {
            $query = MoloniApiClient::products()
                ->queryProduct(['productId' => $productId]);
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Error fetching product by ID: ({0})', ['{0}' => $productId], $e->getData());
        }

        return $query['data']['product']['data'] ?? [];
    }
}
