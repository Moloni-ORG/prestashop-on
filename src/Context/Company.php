<?php

/**
 * 2026 - Moloni.com
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

namespace MoloniOn\Context;

final class Company
{
    private $company;

    private $targetPermissions = [
        'plugins.prestashop',
        'tools.apiClients',
        'tools.webhooks',
        'productsServices.productProperties',
        'productsServices.stocks',
        'productsServices.warehouses',
    ];

    public function __construct(array $company)
    {
        foreach ($company['limits'] ?? [] as $key => $value) {
            if (in_array($value['moduleId'], $this->targetPermissions)) {
                continue;
            }

            unset($company['limits'][$key]);
        }

        $this->company = $company;
    }

    // Gets //

    public function get(string $key)
    {
        return $this->company[$key] ?? null;
    }

    public function getAll(): array
    {
        return $this->company;
    }

    public function getCompanyId(): int
    {
        return (int) $this->company['companyId'];
    }

    public function getCountry(): int
    {
        return (int) $this->company['country']['countryId'];
    }

    // Permissions //

    public function hasPlugin(): bool
    {
        return $this->isAllowed('plugins.prestashop');
    }

    public function hasApiClient(): bool
    {
        return $this->isAllowed('tools.apiClients');
    }

    public function hasWebhooks(): bool
    {
        return $this->isAllowed('tools.webhooks');
    }

    public function hasProperties(): bool
    {
        return $this->isAllowed('productsServices.productProperties');
    }

    public function hasStocks(): bool
    {
        return $this->isAllowed('productsServices.stocks');
    }

    public function hasWarehouses(): bool
    {
        return $this->isAllowed('productsServices.warehouses');
    }

    public function canSyncStock(): bool
    {
        return $this->hasStocks() && $this->hasWarehouses();
    }

    // Privates //

    private function isAllowed(string $resource): bool
    {
        $limits = $this->company['limits'] ?? [];

        foreach ($limits as $limit) {
            if ($limit['moduleId'] !== $resource) {
                continue;
            }

            return $limit['active'] === true;
        }

        return false;
    }
}
