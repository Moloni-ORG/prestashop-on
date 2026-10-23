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

namespace MoloniOn\Actions\Tools;

use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\MoloniException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WebhookDeleteAll
{
    /**
     * Delete all prestashop webhooks
     *
     * @throws MoloniException
     */
    public function handle(): void
    {
        /* Same base as WebhookCreate. The search is a substring match, so a bare path
           (__PS_BASE_URI__) would also match other PrestaShop stores on the company. */
        $baseUrl = defined('_PS_BASE_URL_SSL_') ? _PS_BASE_URL_SSL_ : '';

        $props = [
            'options' => [
                'search' => [
                    'field' => 'url',
                    'value' => $baseUrl . '/api/molonionresource/',
                ],
            ],
        ];

        try {
            $query = MoloniApiClient::hooks()->queryHooks($props);

            if (!empty($query)) {
                $ids = [];

                foreach ($query as $hook) {
                    $ids[] = $hook['hookId'];
                }

                MoloniApiClient::hooks()->mutationHookDelete([
                    'hookId' => $ids,
                ]);
            }
        } catch (MoloniApiException $e) {
            throw new MoloniException('Error deleting hooks', [], [$e->getData()]);
        }
    }
}
