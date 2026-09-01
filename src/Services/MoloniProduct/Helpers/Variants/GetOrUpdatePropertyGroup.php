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
use MoloniOn\Traits\ArrayTrait;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GetOrUpdatePropertyGroup
{
    use ArrayTrait;
    use PrestashopCombinationsTrait;

    /**
     * @var array
     */
    private $prestashopCombinations;

    /**
     * @var string
     */
    private $propertyGroupId;

    public function __construct(\Product $prestashopProduct, string $propertyGroupId)
    {
        $this->prestashopCombinations = $this->preparePrestashopProductAttributes(
            $prestashopProduct->getAttributesGroups(\Configuration::get('PS_LANG_DEFAULT'))
        );
        $this->propertyGroupId = $propertyGroupId;
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
            'propertyGroupId' => $this->propertyGroupId,
        ];

        try {
            $moloniPropertyGroup = MoloniApiClient::propertyGroups()
                ->queryPropertyGroup($queryParams)['data']['propertyGroup']['data'] ?? [];
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Error fetching property group', [], $e->getData());
        }

        /* Propery group is not found, exit process immediately */
        if (empty($moloniPropertyGroup)) {
            throw new MoloniProductException('Error fetching property group', [], $queryParams);
        }

        /*
         * Add any missing properties/values one by one, then map the combinations
         * to their Moloni property pairs.
         */
        return (new AddMissingPropertiesAndValues($moloniPropertyGroup, $this->prestashopCombinations))->handle();
    }
}
