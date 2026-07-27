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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Derives the reference used when a PrestaShop combination is represented as a
 * standalone simple product in Moloni ON (companies without the Product
 * Properties module).
 *
 * The same derivation must be used everywhere the mapping is created or looked
 * up (document builder, sync/stock hooks) so a combination always resolves to
 * the same Moloni product.
 */
class CombinationReference
{
    /**
     * @param \Product $product
     * @param \Combination $combination
     *
     * @return string
     */
    public static function get(\Product $product, \Combination $combination): string
    {
        $reference = (string) $combination->reference;

        if ($reference !== '') {
            return $reference;
        }

        /* Combination has no reference: build a stable key from the ids */
        return (int) $product->id . '-' . (int) $combination->id;
    }
}
