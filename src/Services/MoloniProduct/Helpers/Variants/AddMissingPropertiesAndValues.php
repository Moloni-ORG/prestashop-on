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
use MoloniOn\Enums\Boolean;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Traits\ArrayTrait;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Adds the PrestaShop attributes missing from a Moloni property group.
 *
 * Instead of resending the whole group with propertyGroupUpdate, it issues one
 * granular mutation per missing item (propertyCreate / propertyValueCreate) and
 * folds each response back into the in-memory group tree, so the returned
 * property/variant mapping stays consistent without an extra query.
 */
class AddMissingPropertiesAndValues
{
    use ArrayTrait;

    /**
     * Moloni property group tree (mutated in place while adding missing items)
     *
     * @var array
     */
    private $moloniPropertyGroup;

    /**
     * @var array
     */
    private $prestashopCombinations;

    public function __construct(array $moloniPropertyGroup, array $prestashopCombinations)
    {
        $this->moloniPropertyGroup = $moloniPropertyGroup;
        $this->prestashopCombinations = $prestashopCombinations;
    }

    /**
     * Handler
     *
     * @return array
     *
     * @throws MoloniProductException
     */
    public function handle(): array
    {
        $propertyGroupId = $this->moloniPropertyGroup['propertyGroupId'];

        foreach ($this->prestashopCombinations as $groups) {
            foreach ($groups as $groupName => $attributes) {
                foreach ($attributes as $attribute) {
                    $propExistsKey = $this->findInName($this->moloniPropertyGroup['properties'], $groupName);

                    // Property name exists, we only need to add the missing value
                    if ($propExistsKey !== false) {
                        $property = $this->moloniPropertyGroup['properties'][$propExistsKey];

                        $valueExists = $this->findInCodeWithFallback($property['values'], $attribute);

                        if ($valueExists === false) {
                            $this->createValue($propExistsKey, $attribute);
                        }

                        continue;
                    }

                    // Property name doesn't exist, create the property with this first value
                    $this->createProperty($propertyGroupId, $groupName, $attribute);
                }
            }
        }

        return (new PrepareVariantPropertiesReturn($this->moloniPropertyGroup, $this->prestashopCombinations))->handle();
    }

    /**
     * Create a single value in an existing property and store it in the tree
     *
     * @throws MoloniProductException
     */
    private function createValue(int $propertyKey, string $attribute): void
    {
        $property = $this->moloniPropertyGroup['properties'][$propertyKey];

        $variables = [
            'propertyId' => $property['propertyId'],
            'data' => [
                'code' => $this->cleanReferenceString($attribute),
                'value' => $attribute,
                'ordering' => $this->getNextPropertyOrder($property['values']),
                'visible' => Boolean::YES,
            ],
        ];

        try {
            $mutation = MoloniApiClient::propertyGroups()->mutationPropertyValueCreate($variables);

            $createdValue = $mutation['data']['propertyValueCreate']['data'] ?? [];

            if (empty($createdValue)) {
                throw new MoloniProductException('Failed to create property value "{0}"', ['{0}' => $attribute], ['mutation' => $mutation, 'variables' => $variables]);
            }
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Failed to create property value "{0}"', ['{0}' => $attribute], $e->getData());
        }

        $this->moloniPropertyGroup['properties'][$propertyKey]['values'][] = $createdValue;
    }

    /**
     * Create a single property (with its first value) and store it in the tree
     *
     * @throws MoloniProductException
     */
    private function createProperty(string $propertyGroupId, string $groupName, string $attribute): void
    {
        $variables = [
            'propertyGroupId' => $propertyGroupId,
            'data' => [
                'name' => $groupName,
                'ordering' => $this->getNextPropertyOrder($this->moloniPropertyGroup['properties']),
                'visible' => Boolean::YES,
                'values' => [
                    [
                        'code' => $this->cleanReferenceString($attribute),
                        'value' => $attribute,
                        'ordering' => 1,
                        'visible' => Boolean::YES,
                    ],
                ],
            ],
        ];

        try {
            $mutation = MoloniApiClient::propertyGroups()->mutationPropertyCreate($variables);

            $createdProperty = $mutation['data']['propertyCreate']['data'] ?? [];

            if (empty($createdProperty)) {
                throw new MoloniProductException('Failed to create property "{0}"', ['{0}' => $groupName], ['mutation' => $mutation, 'variables' => $variables]);
            }
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Failed to create property "{0}"', ['{0}' => $groupName], $e->getData());
        }

        $this->moloniPropertyGroup['properties'][] = $createdProperty;
    }

    /**
     * Get next attribute order
     *
     * @param array|null $properties
     *
     * @return int
     */
    private function getNextPropertyOrder(?array $properties = []): int
    {
        $lastOrder = 0;

        if (!empty($properties)) {
            $count = count($properties);
            $lastIndex = $count - 1;

            $lastOrder = $properties[$lastIndex]['ordering'] ?? 0;
        }

        return $lastOrder + 1;
    }
}
