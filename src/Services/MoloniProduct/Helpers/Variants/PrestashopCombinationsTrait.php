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

namespace MoloniOn\Services\MoloniProduct\Helpers\Variants;

if (!defined('_PS_VERSION_')) {
    exit;
}

trait PrestashopCombinationsTrait
{
    /**
     * Group a PrestaShop product's attribute rows by combination and attribute group.
     *
     * @param array|null $productAttributes rows from Product::getAttributesGroups()
     *
     * @return array
     */
    private function preparePrestashopProductAttributes(?array $productAttributes = []): array
    {
        /**
         * [
         *      'combination_id => [
         *          'group_name' => [
         *              'attribute_a',
         *              'attribute_b',
         *              ...
         *          ]
         *      ]
         * ]
         */
        $result = [];

        foreach ($productAttributes as $attribute) {
            $combinationId = (int) $attribute['id_product_attribute'];
            $groupName = $attribute['group_name'];
            $attributeName = $attribute['attribute_name'];

            if (!isset($result[$combinationId][$groupName])) {
                $result[$combinationId][$groupName] = [];
            }

            $result[$combinationId][$groupName][] = $attributeName;
        }

        return $result;
    }
}
