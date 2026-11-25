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

namespace MoloniOn\Controller\Admin\Login;

use MoloniOn\Api\MoloniApi;
use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Context\Company;
use MoloniOn\Controller\Admin\MoloniController;
use MoloniOn\Entity\MoloniOnApp;
use MoloniOn\Enums\MoloniRoutes;
use MoloniOn\Exceptions\MoloniException;
use MoloniOn\Form\Login\LoginFormType;
use MoloniOn\Helpers\MoloniOAuth;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Login extends MoloniController
{
    public function home(): Response
    {
        $form = $this->createForm(LoginFormType::class, null, [
            'url' => $this->generateUrl(MoloniRoutes::LOGIN_SUBMIT),
        ]);

        return $this->display('login/Login.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Starts an OAuth public-client connection: registers (or reuses, dedup is server-side) a public
     * client for this store, then redirects to the Moloni ON authorization screen with PKCE. No API
     * client / Client secret to paste: the module's own front callback (no employee token, stable
     * across requests) is the redirect_uri.
     */
    public function submit(Request $request): RedirectResponse
    {
        $form = $this->createForm(LoginFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $msg = $this->trans('Form is not valid.', 'Modules.Molonion.Errors');

            $this->addWarningMessage($msg);

            return $this->redirectToLogin();
        }

        $this->deleteApp();

        $redirectUri = MoloniOAuth::getCallbackUrl();

        try {
            $clientId = MoloniApi::registerClient($redirectUri);
        } catch (MoloniException $e) {
            $msg = $this->trans($e->getMessage(), 'Modules.Molonion.Errors', $e->getIdentifiers());
            $this->addErrorMessage($msg, $e->getData());

            return $this->redirectToLogin();
        }

        $entityManager = $this->getDoctrine()->getManager();

        $moloniApp = new MoloniOnApp();
        $moloniApp->setClientId($clientId);
        $moloniApp->setClientSecret('');
        $moloniApp->setCompanyId(0);
        $moloniApp->setAccessToken('');
        $moloniApp->setRefreshToken('');
        $moloniApp->setShopId(\Shop::getContextShopID() ?? 0);
        $moloniApp->setAccessTime(0);

        $entityManager->persist($moloniApp);
        $entityManager->flush();

        $verifier = MoloniOAuth::generateVerifier();
        $state = MoloniOAuth::generateState();

        // Pre-built now (admin context has the employee's token); the front callback has none, and
        // can't build one itself (its container doesn't load this module's own services)
        $retrieveCodeUrl = $this->generateUrl(MoloniRoutes::LOGIN_RETRIEVE_CODE, [], UrlGeneratorInterface::ABSOLUTE_URL);

        MoloniOAuth::storePending($state, $verifier, $redirectUri, $retrieveCodeUrl, $clientId);

        $url = $this->moloniContext->configs()->getApiUrl() . '/auth/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'code_challenge' => MoloniOAuth::generateChallenge($verifier),
            'code_challenge_method' => 'S256',
            'state' => $state,
        ]);

        return $this->redirect($url);
    }

    /**
     * Finishes the OAuth public-client connection. Reached two ways: a legacy client-id/secret login
     * (no pending record, $code only) hits this directly; the new PKCE flow is bounced here by the
     * module's front callback (which only verified `state`, without consuming it — this is where the
     * grant actually happens, since this admin action has the full container + Doctrine the front
     * callback does not).
     */
    public function retrieveCode(): RedirectResponse
    {
        $code = \Tools::getValue('code', '');
        $state = \Tools::getValue('state', '');
        $error = \Tools::getValue('error', '');

        try {
            $pending = MoloniOAuth::peekPending();
            $isPkceCallback = $pending !== null && $state !== '';

            if ($isPkceCallback) {
                if (!hash_equals((string) $pending['state'], $state)) {
                    throw new MoloniException('We could not connect your store to Moloni ON. Please try again.');
                }

                // Single use: consume now that the state is verified
                MoloniOAuth::clearPending();

                if ($error !== '' || empty($code)) {
                    throw new MoloniException('We could not connect your store to Moloni ON. Please try again.');
                }

                $this->ensureAppForPendingConnect($pending);

                MoloniApi::login($code, (string) $pending['verifier'], (string) $pending['redirect_uri']);
            } else {
                if (empty($code)) {
                    throw new MoloniException('Code cannot be empty.');
                }

                MoloniApi::login($code);
            }
        } catch (MoloniException $e) {
            $msg = $this->trans($e->getMessage(), 'Modules.Molonion.Errors', $e->getIdentifiers());
            $this->addErrorMessage($msg, $e->getData());

            return $this->redirectToLogin();
        }

        return $this->redirectToCompanySelect();
    }

    /**
     * Recreates the app row for a pending connect if it was wiped in the meantime — e.g. a second
     * admin tab navigating to a fully-authenticated route while this connect was still waiting on
     * Moloni's consent screen triggers MoloniController::redirectToLogin(), which deletes it.
     *
     * @param array $pending
     *
     * @return void
     */
    private function ensureAppForPendingConnect(array $pending): void
    {
        $app = $this->moloniContext->getApp();

        if ($app && $app->getClientId() === ($pending['client_id'] ?? null)) {
            return;
        }

        $entityManager = $this->getDoctrine()->getManager();

        $app = new MoloniOnApp();
        $app->setClientId((string) $pending['client_id']);
        $app->setClientSecret('');
        $app->setCompanyId(0);
        $app->setAccessToken('');
        $app->setRefreshToken('');
        $app->setShopId(\Shop::getContextShopID() ?? 0);
        $app->setAccessTime(0);

        $entityManager->persist($app);
        $entityManager->flush();

        MoloniApi::setApp($app);
    }

    public function companySelect()
    {
        $companies = [];

        try {
            $queryCompanies = MoloniApiClient::companies()
                ->queryCompanies();

            if (empty($queryCompanies)) {
                throw new MoloniException('You have no companies.');
            }

            foreach ($queryCompanies as $company) {
                $object = new Company($company);

                if (!$object->getCompanyId()) {
                    continue;
                }

                if (!$object->get('isConfirmed')) {
                    continue;
                }

                if (!$object->hasApiClient()) {
                    continue;
                }

                $companies[] = $object->getAll();
            }
        } catch (MoloniException $e) {
            $msg = $this->trans($e->getMessage(), 'Modules.Molonion.Errors', $e->getIdentifiers());
            $this->addErrorMessage($msg, $e->getData());

            return $this->redirectToLogin();
        }

        return $this->display(
            'login/Companies.twig',
            [
                'companies' => $companies,
                'submit_route' => MoloniRoutes::LOGIN_COMPANY_SUBMIT,
                'logoutRoute' => MoloniRoutes::TOOLS_LOGOUT,
            ]
        );
    }

    public function companySelectSubmit(?int $companyId): RedirectResponse
    {
        try {
            if (!is_numeric($companyId) || $companyId < 0) {
                throw new MoloniException('ID is invalid');
            }

            $moloniApp = $this->moloniContext->getApp();

            if (!$moloniApp) {
                throw new MoloniException('Missing information in database');
            }

            $moloniApp->setCompanyId($companyId);

            $entityManager = $this
                ->getDoctrine()
                ->getManager();
            $entityManager->persist($moloniApp);
            $entityManager->flush();

            $msg = $this->trans('Company selected successfully', 'Modules.Molonion.Common');
            $this->addSuccessMessage($msg);
        } catch (MoloniException $e) {
            $msg = $this->trans($e->getMessage(), 'Modules.Molonion.Errors', $e->getIdentifiers());
            $this->addErrorMessage($msg, $e->getData());

            return $this->redirectToCompanySelect();
        }

        return $this->redirectToSettings();
    }
}
