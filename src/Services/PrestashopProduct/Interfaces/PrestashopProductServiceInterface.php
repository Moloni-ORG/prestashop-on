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

namespace MoloniOn\Services\PrestashopProduct\Interfaces;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface PrestashopProductServiceInterface
{
    /**
     * Execute the service work (create/update/stock sync).
     *
     * @return void
     */
    public function run(): void;

    /**
     * Write the log describing what the service did.
     *
     * Callers opt into logging by invoking this after run(); bulk/background
     * callers can skip it to avoid flooding the logs.
     *
     * @return void
     */
    public function saveLog(): void;
}
