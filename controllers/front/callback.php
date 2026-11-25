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

use MoloniOn\Helpers\MoloniOAuth;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * OAuth (PKCE) callback for the public-client connect flow.
 *
 * This MUST be a module front controller, not an admin route: PrestaShop's admin routes require a
 * valid per-employee token in the request or redirect to "admin_security_compromised"
 * (TokenizedUrlsListener), but Moloni ON's redirect only ever carries ?code/?state/?error. A front
 * controller link carries no such token and is stable across requests, so it's used verbatim as the
 * redirect_uri at register, authorize and grant.
 *
 * It does no grant itself: the front container only loads this module's top-level
 * `config/services.yml` (no `molonion.context`/Doctrine access the way an admin request has one), so
 * all this does is verify `state` (hash_equals, without consuming it — the admin side does that) and
 * hand off to the tokenized admin route Login::submit() pre-built, which performs the real grant with
 * a full container and the employee's own session.
 */
class MolonionCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        $pending = MoloniOAuth::peekPending();

        $state = (string) Tools::getValue('state', '');
        $code = (string) Tools::getValue('code', '');
        $error = (string) Tools::getValue('error', '');

        $stateIsValid = $pending !== null && $state !== '' && hash_equals((string) $pending['state'], $state);

        if (!$stateIsValid) {
            // Untrusted request: never reveal the admin URL/token to it, and never touch a real
            // pending connect this request isn't part of.
            header('HTTP/1.1 404 Not Found');
            exit;
        }

        $url = (string) $pending['retrieve_code_url'];
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query(array_filter(
            ['code' => $code, 'state' => $state, 'error' => $error],
            static function ($value) {
                return $value !== '';
            }
        ));

        // $url is already absolute (ABSOLUTE_URL), so Tools::redirectAdmin() leaves it untouched
        // instead of resolving _PS_ADMIN_DIR_, which isn't defined in this front-office request.
        Tools::redirectAdmin($url);
    }

    /**
     * Moloni ON's redirect must always reach postProcess() above, maintenance mode or not.
     *
     * @return void
     */
    protected function displayMaintenancePage()
    {
    }

    /**
     * Moloni ON's redirect must always reach postProcess() above regardless of geoloc restrictions.
     *
     * @return void
     */
    protected function displayRestrictedCountryPage()
    {
    }
}
