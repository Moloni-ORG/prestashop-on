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

namespace MoloniOn\Traits;

if (!defined('_PS_VERSION_')) {
    exit;
}

trait MoloniProductReferenceTrait
{
    /**
     * Returns the Moloni product whose reference exactly matches $reference.
     *
     * Moloni's "reference eq" search can return partial/substring matches, so we
     * pick the exact one here. References are unique in Moloni, so there is at
     * most one match.
     */
    private function findExactReferenceMatch(array $query, string $reference): array
    {
        foreach ($query as $product) {
            if (isset($product['reference']) && $product['reference'] === $reference) {
                return $product;
            }
        }

        return [];
    }
}
