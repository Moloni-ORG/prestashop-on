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

        upgrade_module_3_1_04_product_associations();
    } catch (Throwable $e) {
        return false;
    }

    return true;
}

/**
 * Adds a unique mapping key to the product associations table so simple
 * products can be matched by stored id (not only by reference).
 *
 * Existing installs predate the key: their combination id may be NULL and could
 * in theory contain duplicates, so normalise and de-duplicate before adding it.
 *
 * @return void
 */
function upgrade_module_3_1_04_product_associations(): void
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'moloni_on_product_associations';

    /* Normalise legacy NULLs so they group consistently under the unique key */
    $db->execute('UPDATE `' . $table . '` SET `ps_combination_id` = 0 WHERE `ps_combination_id` IS NULL');
    $db->execute('UPDATE `' . $table . '` SET `company_id` = 0 WHERE `company_id` IS NULL');

    /* Drop duplicates, keeping the most recent row per (company, product, combination) */
    $db->execute(
        'DELETE a FROM `' . $table . '` a ' .
        'INNER JOIN `' . $table . '` b ' .
        'ON a.`company_id` = b.`company_id` ' .
        'AND a.`ps_product_id` = b.`ps_product_id` ' .
        'AND a.`ps_combination_id` = b.`ps_combination_id` ' .
        'AND a.`id` < b.`id`'
    );

    $db->execute('ALTER TABLE `' . $table . '` MODIFY `ps_combination_id` INT(11) NOT NULL DEFAULT 0');
    $db->execute('ALTER TABLE `' . $table . '` MODIFY `company_id` INT(11) NOT NULL DEFAULT 0');

    /* Add the unique key only when missing, so the upgrade stays idempotent */
    $indexExists = (int) $db->getValue(
        'SELECT COUNT(1) FROM information_schema.STATISTICS ' .
        "WHERE table_schema = DATABASE() AND table_name = '" . $table . "' AND index_name = 'uniq_ps_assoc'"
    );

    if ($indexExists === 0) {
        $db->execute('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uniq_ps_assoc` (`company_id`, `ps_product_id`, `ps_combination_id`)');
    }
}
