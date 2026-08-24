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

namespace MoloniOn\Api\Endpoints;

use MoloniOn\Exceptions\MoloniApiException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PropertyGroups extends Endpoint
{
    /**
     * Get multiple property groups
     *
     * @param array|null $variables
     *
     * @return array
     *
     * @throws MoloniApiException
     */
    public function queryPropertyGroups(?array $variables = []): array
    {
        $query = $this->loadQuery('propertyGroups');

        return $this->paginatedPost($query, $variables, 'propertyGroups');
    }

    /**
     * Get single property group
     *
     * @param array|null $variables
     *
     * @return array
     *
     * @throws MoloniApiException
     */
    public function queryPropertyGroup(?array $variables = []): array
    {
        $query = $this->loadQuery('propertyGroup');

        return $this->simplePost($query, $variables);
    }

    /**
     * Create a property group
     *
     * @param array|null $variables
     *
     * @return array
     *
     * @throws MoloniApiException
     */
    public function mutationPropertyGroupCreate(?array $variables = []): array
    {
        $query = $this->loadMutation('propertyGroupCreate');

        return $this->simplePost($query, $variables);
    }

    /**
     * Add a single property to an existing property group
     *
     * @param array|null $variables
     *
     * @return array
     *
     * @throws MoloniApiException
     */
    public function mutationPropertyCreate(?array $variables = []): array
    {
        $query = $this->loadMutation('propertyCreate');

        return $this->simplePost($query, $variables);
    }

    /**
     * Add a single value to an existing property
     *
     * @param array|null $variables
     *
     * @return array
     *
     * @throws MoloniApiException
     */
    public function mutationPropertyValueCreate(?array $variables = []): array
    {
        $query = $this->loadMutation('propertyValueCreate');

        return $this->simplePost($query, $variables);
    }
}
