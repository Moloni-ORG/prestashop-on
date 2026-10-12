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

namespace MoloniOn\Helpers;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductType
{
    /**
     * Whether the product is a product with combinations
     *
     * Product::$product_type only exists since PrestaShop 8; on 1.7 a product with combinations is
     * recognised by its combinations alone.
     *
     * @param \Product $product
     *
     * @return bool
     */
    public static function hasCombinations(\Product $product): bool
    {
        if (property_exists($product, 'product_type') && $product->product_type !== 'combinations') {
            return false;
        }

        return (bool) $product->hasCombinations();
    }
}
