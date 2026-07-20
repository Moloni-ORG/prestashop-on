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

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Module upgrade for version 3.1.04.
 *
 * PrestaShop runs every upgrade/upgrade-<version>.php whose <version> is
 * greater than the currently installed version and lower than or equal to the
 * new version, ordered by version. The function name must be
 * "upgrade_module_<version>" with the dots of the version replaced by
 * underscores.
 *
 * To add a migration for a future release, copy this file to
 * upgrade-<new-version>.php and rename the function accordingly. New tables are
 * picked up automatically by upgradeDatabase() (the SQL uses
 * "CREATE TABLE IF NOT EXISTS"); for column changes, run explicit
 * "ALTER TABLE" statements here.
 *
 * @param CoreModule $module
 *
 * @return bool
 */
function upgrade_module_3_1_04($module): bool
{
    try {
        (new MoloniOn\Activators\Install($module))->upgradeDatabase();
    } catch (Throwable $e) {
        return false;
    }

    return true;
}
