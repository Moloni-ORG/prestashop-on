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
 * Derives the reference used to represent a PrestaShop product in Moloni ON.
 *
 * The same derivation must be used wherever a product is looked up or written
 * (product sync services, finder, document builder) so a product always
 * resolves to the same Moloni reference: its PrestaShop reference, or its id as
 * a stable fallback when the reference is empty.
 */
class ProductReference
{
    /**
     * @param \Product $product
     *
     * @return string
     */
    public static function fromPrestashopProduct(\Product $product): string
    {
        $reference = $product->reference;

        if (empty($reference)) {
            $reference = (string) $product->id;
        }

        return (string) $reference;
    }
}
