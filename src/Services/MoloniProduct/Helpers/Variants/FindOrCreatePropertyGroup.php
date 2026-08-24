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

use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\Product\MoloniProductException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class FindOrCreatePropertyGroup
{
    use PrestashopCombinationsTrait;

    /**
     * @var array
     */
    private $prestashopCombinations;

    public function __construct(\Product $prestashopProduct)
    {
        $this->prestashopCombinations = $this->preparePrestashopProductAttributes(
            $prestashopProduct->getAttributesGroups(\Configuration::get('PS_LANG_DEFAULT'))
        );
    }

    /**
     * @throws MoloniProductException
     */
    public function handle(): array
    {
        if (empty($this->prestashopCombinations)) {
            return [];
        }

        $queryParams = [
            'options' => [
                'search' => [
                    'field' => 'name',
                    'value' => CreateEntirePropertyGroup::PROPERTY_GROUP_NAME,
                ],
            ],
        ];

        try {
            $moloniPropertyGroups = MoloniApiClient::propertyGroups()->queryPropertyGroups($queryParams);
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Error fetching property groups', [], $e->getData());
        }

        // Always reuse the "Prestashop" group when it already exists
        $prestashopGroup = $this->findGroupByName(
            $moloniPropertyGroups,
            CreateEntirePropertyGroup::PROPERTY_GROUP_NAME
        );

        // No "Prestashop" group yet, create it from scratch
        if ($prestashopGroup === false) {
            return (new CreateEntirePropertyGroup($moloniPropertyGroups, $this->prestashopCombinations))->handle();
        }

        /*
         * Reuse the existing "Prestashop" group, adding any missing
         * properties/values, then map the combinations to their Moloni pairs.
         */
        return (new AddMissingPropertiesAndValues($prestashopGroup, $this->prestashopCombinations))->handle();
    }

    //          AUXILIARY          //

    /**
     * Find a property group by name (case-insensitive)
     *
     * @param array $moloniPropertyGroups
     * @param string $needle
     *
     * @return false|array
     */
    private function findGroupByName(array $moloniPropertyGroups, string $needle)
    {
        foreach ($moloniPropertyGroups as $moloniPropertyGroup) {
            if (empty($moloniPropertyGroup['propertyGroupId'])) {
                continue;
            }

            if (strtolower($moloniPropertyGroup['name'] ?? '') === strtolower($needle)) {
                return $moloniPropertyGroup;
            }
        }

        return false;
    }
}
