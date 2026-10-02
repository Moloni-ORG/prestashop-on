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

namespace MoloniOn\Exceptions\Product;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * A product could not be created in Moloni ON because the company's plan product limit
 * has been reached.
 *
 * Thrown up front when the company limits say the plan is full (see
 * Company::canCreateProducts), or when Moloni ON itself refuses the create. Product sync
 * (product save, export) logs it as a warning; while creating a document it fails the
 * document with this message.
 */
class MoloniProductLimitException extends MoloniProductException
{
    /**
     * Message (translation key) for a product that could not be created because of the
     * plan's product limit. {0} is the product reference.
     */
    public const MESSAGE = 'Could not create "{0}" in Moloni ON: the plan\'s product limit has been reached.';

    /**
     * What Moloni ON answers a product create with once the plan's product limit is reached
     */
    private const API_ERROR = 'Number of items is over the allowed limit.';

    public function __construct(string $reference, array $data = [])
    {
        parent::__construct(self::MESSAGE, ['{0}' => $reference], $data);
    }

    /**
     * Whether a productCreate response was refused because of the plan's product limit
     *
     * @param array $mutation The decoded productCreate response
     */
    public static function isApiError(array $mutation): bool
    {
        $errors = $mutation['data']['productCreate']['errors'] ?? [];

        foreach ((array) $errors as $error) {
            if (($error['msg'] ?? '') === self::API_ERROR) {
                return true;
            }
        }

        return false;
    }
}
