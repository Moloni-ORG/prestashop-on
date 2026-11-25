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

namespace MoloniOn\Helpers;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Helpers for the public-client OAuth (PKCE) connect flow: the stable callback URL, the PKCE/state
 * values, and the short-lived "pending connect" record kept in PrestaShop's own Configuration table
 * (global, not shop-scoped, so a multi-shop setup can't shadow it) between Login::submit() (admin
 * context) and the module's front callback controller, since neither a session nor the app row
 * (wiped on every reconnect) can carry it.
 */
class MoloniOAuth
{
    private const PENDING_CONFIG_KEY = 'MOLONI_ON_OAUTH_PENDING';

    /** Pending connect requests are abandoned after this many seconds */
    private const PENDING_TTL = 1800;

    /**
     * The stable redirect_uri for the OAuth flow: a module front controller link carries no
     * employee token and no per-request nonce, so it matches byte-for-byte across register,
     * authorize and grant.
     *
     * $ssl is explicitly true, not null: on PrestaShop 1.7, Link::getModuleLink(..., null) only
     * resolves to https when BOTH PS_SSL_ENABLED and the stricter PS_SSL_ENABLED_EVERYWHERE are on,
     * under-reporting a shop that genuinely serves https (e.g. SSL terminated by a reverse proxy).
     * true only requires PS_SSL_ENABLED (matching PS8/9's simpler check), and still falls back to
     * http when SSL is off entirely (e.g. a local loopback dev store), since Link::getBaseLink()
     * ANDs it with the shop's own ssl_enable flag either way.
     */
    public static function getCallbackUrl(): string
    {
        return \Context::getContext()->link->getModuleLink('molonion', 'callback', [], true);
    }

    public static function generateVerifier(): string
    {
        return self::base64UrlEncode(random_bytes(32));
    }

    public static function generateChallenge(string $verifier): string
    {
        return self::base64UrlEncode(hash('sha256', $verifier, true));
    }

    public static function generateState(): string
    {
        return self::base64UrlEncode(random_bytes(16));
    }

    /**
     * Persists a pending connect: the state/verifier pair, the exact redirect_uri used to register
     * and authorize (reused verbatim at grant time), the registered client id (to recover the app
     * row if something wipes it mid-flight), and the tokenized admin URL that finishes the grant
     * (built while still in admin context, since the front callback has none).
     */
    public static function storePending(string $state, string $verifier, string $redirectUri, string $retrieveCodeUrl, string $clientId): void
    {
        \Configuration::updateGlobalValue(self::PENDING_CONFIG_KEY, json_encode([
            'state' => $state,
            'verifier' => $verifier,
            'redirect_uri' => $redirectUri,
            'retrieve_code_url' => $retrieveCodeUrl,
            'client_id' => $clientId,
            'expires' => time() + self::PENDING_TTL,
        ]));
    }

    /**
     * Reads the pending connect without consuming it, so a stray/forged callback (wrong or missing
     * state) can be bounced back without destroying a real connect still in flight.
     *
     * @return array{state: string, verifier: string, redirect_uri: string, retrieve_code_url: string, client_id: string}|null
     */
    public static function peekPending(): ?array
    {
        $raw = \Configuration::getGlobalValue(self::PENDING_CONFIG_KEY);

        if (empty($raw)) {
            return null;
        }

        $pending = json_decode($raw, true);

        if (!is_array($pending) || empty($pending['state']) || empty($pending['expires'])) {
            return null;
        }

        if ((int) $pending['expires'] < time()) {
            return null;
        }

        return $pending;
    }

    /**
     * Consumes the pending connect so its state can't be replayed. Only call this once the state
     * has been verified (hash_equals) against peekPending()'s result.
     */
    public static function clearPending(): void
    {
        \Configuration::deleteByName(self::PENDING_CONFIG_KEY);
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
