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

namespace MoloniOn\Api;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use MoloniOn\Context\Configurations;
use MoloniOn\Entity\MoloniOnApp;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\MoloniLoginException;
use MoloniOn\Guzzle\GuzzleWrapper;
use MoloniOn\Mails\AuthenticationExpiredMail;
use MoloniOn\MoloniContext;
use MoloniOn\Tools\Settings;

if (!defined('_PS_VERSION_')) {
    exit;
}

class MoloniApi
{
    /**
     * EntityManager
     *
     * @var EntityManagerInterface
     */
    private static $entityManager;

    /**
     * @var MoloniOnApp|null
     */
    private static $app;

    /**
     * @var Configurations
     */
    private static $configurations;

    /**
     * @var GuzzleWrapper
     */
    private static $guzzle;

    public function __construct(EntityManagerInterface $entityManager, MoloniContext $context)
    {
        $configurations = $context->configs();

        self::$app = $context->getApp();
        self::$entityManager = $entityManager;
        self::$configurations = $configurations;

        self::$guzzle = new GuzzleWrapper($configurations);
    }

    /**
     * Overrides the app loaded for this request. Used to recover a pending connect whose app row was
     * wiped in the meantime (e.g. a second admin tab's redirectToLogin() deleting it while this
     * connect was still waiting on Moloni's consent screen).
     *
     * @param MoloniOnApp $app
     *
     * @return void
     */
    public static function setApp(MoloniOnApp $app): void
    {
        self::$app = $app;
    }

    //          Requests          //

    /**
     * Login action
     *
     * @param string $code
     * @param string $codeVerifier PKCE verifier, only sent for a public client (empty stored secret)
     * @param string $redirectUri Must match the one used to obtain $code, only sent alongside $codeVerifier
     *
     * @return bool
     *
     * @throws MoloniLoginException
     */
    public static function login(string $code, string $codeVerifier = '', string $redirectUri = ''): bool
    {
        if (empty($code)) {
            throw new MoloniLoginException('Code missing');
        }

        $url = self::$configurations->getApiUrl() . '/auth/grant';
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $params = [
            'grantType' => 'authorization_code',
            'apiClientId' => self::$app->getClientId(),
            'code' => $code,
        ];

        // A non-empty stored secret is a legacy (merchant-made API client) login: keep its request exactly as before
        if (!empty(self::$app->getClientSecret())) {
            $params['clientSecret'] = self::$app->getClientSecret();
        }

        if (!empty($codeVerifier)) {
            $params['code_verifier'] = $codeVerifier;
        }

        if (!empty($redirectUri)) {
            $params['redirect_uri'] = $redirectUri;
        }

        try {
            $body = self::$guzzle->post($url, $headers, $params);
        } catch (MoloniApiException $e) {
            throw new MoloniLoginException('The client credentials are invalid', [], $e->getData());
        }

        if (empty($body)) {
            throw new MoloniLoginException('Request error');
        }

        if (!$body['accessToken'] || !$body['refreshToken']) {
            throw new MoloniLoginException('Error fetching tokens', [], ['response' => $body]);
        }

        try {
            self::$app->setAccessToken($body['accessToken']);
            self::$app->setRefreshToken($body['refreshToken']);
            self::$app->setAccessTime(time());

            self::$entityManager->persist(self::$app);
            self::$entityManager->flush();
        } catch (ORMException $e) {
            throw new MoloniLoginException($e->getMessage());
        }

        return true;
    }

    /**
     * Registers (or reuses, dedup is server-side) a public OAuth client for this store's redirect_uri,
     * so a merchant can connect without creating an API client or pasting a Client ID/secret.
     *
     * @param string $redirectUri
     *
     * @return string The registered client id
     *
     * @throws MoloniLoginException
     */
    public static function registerClient(string $redirectUri): string
    {
        $url = self::$configurations->getApiUrl() . '/auth/register';
        $headers = [
            'Content-Type' => 'application/json',
        ];

        // The store's host makes the name unique, so the server's dedup finds this store's client again
        $host = parse_url($redirectUri, PHP_URL_HOST) ?: $redirectUri;
        $params = [
            'client_name' => "Moloni ON PrestaShop ($host)",
            'redirect_uris' => [$redirectUri],
        ];

        try {
            $body = self::$guzzle->post($url, $headers, $params);
        } catch (MoloniApiException $e) {
            throw self::registerExceptionFor($e);
        }

        if (empty($body['client_id'])) {
            throw new MoloniLoginException('We could not connect your store to Moloni ON. Please try again.', [], ['response' => $body]);
        }

        return $body['client_id'];
    }

    /**
     * Maps a failed /auth/register call to a user-facing message
     *
     * @param MoloniApiException $e
     *
     * @return MoloniLoginException
     */
    private static function registerExceptionFor(MoloniApiException $e): MoloniLoginException
    {
        $data = $e->getData();
        $response = json_decode((string) ($data['response'] ?? ''), true);

        if (is_array($response) && ($response['error'] ?? '') === 'invalid_redirect_uri') {
            return new MoloniLoginException('Your store must use HTTPS to connect to Moloni ON.', [], $data);
        }

        if ((int) ($data['status'] ?? 0) === 429) {
            return new MoloniLoginException('Too many connection attempts. Please try again later.', [], $data);
        }

        return new MoloniLoginException('We could not connect your store to Moloni ON. Please try again.', [], $data);
    }

    /**
     * Refresh tokens action
     *
     * @return bool
     */
    public static function refreshTokens(): bool
    {
        $url = self::$configurations->getApiUrl() . '/auth/grant';
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $params = [
            'grantType' => 'refresh_token',
            'apiClientId' => self::$app->getClientId(),
            'refreshToken' => self::$app->getRefreshToken(),
        ];

        // A non-empty stored secret is a legacy (merchant-made API client) login: keep its request exactly as before
        if (!empty(self::$app->getClientSecret())) {
            $params['clientSecret'] = self::$app->getClientSecret();
        }

        try {
            $body = self::$guzzle->post($url, $headers, $params);

            if (empty($body)) {
                throw new MoloniLoginException('Request error');
            }

            if (!$body['accessToken'] || !$body['refreshToken']) {
                throw new MoloniLoginException('Error fetching tokens', [], ['response' => $body]);
            }
        } catch (MoloniApiException $e) {
            if (!empty(Settings::get('alertEmail'))) {
                (new AuthenticationExpiredMail(Settings::get('alertEmail'), ['message' => $e->getMessage()]))->handle();
            }

            return false;
        } catch (MoloniLoginException $e) {
            if (!empty(Settings::get('alertEmail'))) {
                (new AuthenticationExpiredMail(Settings::get('alertEmail'), $e->getData()))->handle();
            }

            return false;
        }

        try {
            self::$app->setAccessToken($body['accessToken']);
            self::$app->setRefreshToken($body['refreshToken']);
            self::$app->setAccessTime(time());

            self::$entityManager->persist(self::$app);
            self::$entityManager->flush();
        } catch (ORMException $e) {
            if (!empty(Settings::get('alertEmail'))) {
                (new AuthenticationExpiredMail(Settings::get('alertEmail'), ['message' => $e->getMessage()]))->handle();
            }

            return false;
        }

        return true;
    }

    /**
     * Make authenticated request
     *
     * @param array|null $data
     *
     * @return array
     *
     * @throws MoloniApiException
     */
    public static function post(?array $data = []): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (self::$app) {
            if (!empty(self::$app->getCompanyId()) && isset($data['variables']) && !isset($data['variables']['companyId'])) {
                $data['variables']['companyId'] = self::$app->getCompanyId();
            }

            if (!empty(self::$app->getAccessToken())) {
                $headers['Authorization'] = 'bearer ' . self::$app->getAccessToken();
            }
        }

        if (empty($data['variables'])) {
            unset($data['variables']);
        }

        $body = self::$guzzle->post(self::$configurations->getApiUrl(), $headers, $data);

        return empty($body) ? [] : $body;
    }

    /**
     * Make authenticated request with file
     *
     * @param array|null $operations
     * @param string|null $map
     * @param array|null $files
     *
     * @return array
     *
     * @throws MoloniApiException
     */
    public static function postWithFile(?array $operations = [], ?string $map = '', ?array $files = []): array
    {
        $accessToken = '';

        if (self::$app) {
            $companyId = self::$app->getCompanyId();

            if (!empty($companyId) && isset($operations['variables']) && !isset($operations['variables']['companyId'])) {
                $operations['variables']['companyId'] = $companyId;
            }

            $accessToken = self::$app->getAccessToken();
        }

        $response = self::$guzzle->postWithFile($operations, $map, $files, $accessToken);

        return $response ?? [];
    }

    //          Verifications          //

    /**
     * Verifies if company is selected
     *
     * @return bool
     */
    public static function hasValidCompany(): bool
    {
        if (empty(self::$app)) {
            return false;
        }

        return !empty(self::$app->getCompanyId());
    }

    /**
     * Verifies Moloni tokens validity
     *
     * @return bool
     */
    public static function hasValidAuthentication(): bool
    {
        if (empty(self::$app)) {
            return false;
        }

        if (empty(self::$app->getAccessToken()) || empty(self::$app->getRefreshToken())) {
            return false;
        }

        if (self::$app->isValidAccessToken()) {
            return true;
        }

        return self::$app->isValidRefreshToken() && self::refreshTokens();
    }

    /**
     * Verifies if company is selected and tokens validity
     *
     * @return bool
     */
    public static function hasAuthenticationAndCompany(): bool
    {
        if (!self::hasValidAuthentication()) {
            return false;
        }

        if (!self::hasValidCompany()) {
            return false;
        }

        /* Load company to context */
        MoloniContext::instance()->loadCompany();

        return true;
    }
}
