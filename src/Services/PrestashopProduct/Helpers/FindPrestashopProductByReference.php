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

use MoloniOn\Enums\Boolean;
use MoloniOn\Tools\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Finds the PrestaShop product matching a Moloni product's reference.
 *
 * Extracted from the old product builders so orchestrators can decide between a
 * create and an update service before instantiating one. Returns an existing
 * loaded product, a reference-fallback match, or a fresh empty product.
 */
class FindPrestashopProductByReference
{
    /**
     * Build the target PrestaShop product from a Moloni product.
     *
     * @param array $moloniProduct
     *
     * @return \Product Existing product (id > 0) or a new empty product
     */
    public static function fromMoloniProduct(array $moloniProduct): \Product
    {
        $reference = $moloniProduct['reference'] ?? '';
        $languageId = \Configuration::get('PS_LANG_DEFAULT');

        $productId = (int) \Product::getIdByReference($reference);

        if ($productId > 0) {
            return new \Product($productId, true, $languageId);
        }

        if ((int) Settings::get('productReferenceFallback') === Boolean::YES && is_numeric($reference)) {
            return new \Product((int) $reference, true, $languageId);
        }

        return new \Product();
    }
}
