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
use MoloniOn\Repository\MoloniOnAppRepository;
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

    public function submit(Request $request): RedirectResponse
    {
        $form = $this->createForm(LoginFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $msg = $this->trans('Form is not valid.', 'Modules.Molonion.Errors');

            $this->addWarningMessage($msg);

            return $this->redirectToLogin();
        }

        $entityManager = $this->getDoctrine()->getManager();

        /** @var MoloniOnAppRepository $appRepository */
        $appRepository = $entityManager->getRepository(MoloniOnApp::class);
        $appRepository->deleteApp();

        $formData = $form->getData();

        $clientId = trim($formData['clientID']);
        $clientSecret = trim($formData['clientSecret']);

        $moloniApp = new MoloniOnApp();
        $moloniApp->setClientId($clientId);
        $moloniApp->setClientSecret($clientSecret);
        $moloniApp->setCompanyId(0);
        $moloniApp->setAccessToken('');
        $moloniApp->setRefreshToken('');
        $moloniApp->setShopId(\Shop::getContextShopID() ?? 0);
        $moloniApp->setAccessTime(0);

        $entityManager->persist($moloniApp);
        $entityManager->flush();

        $redirectUri = defined('_PS_BASE_URL_SSL_') ? _PS_BASE_URL_SSL_ : '';
        $redirectUri .= $this->generateUrl(MoloniRoutes::LOGIN_RETRIEVE_CODE, [], UrlGeneratorInterface::ABSOLUTE_URL);

        $url = $this->moloniContext->configs()->getApiUrl();
        $url .= "/auth/authorize?apiClientId=$clientId&redirectUri=$redirectUri";

        return $this->redirect($url);
    }

    public function retrieveCode(): RedirectResponse
    {
        $code = \Tools::getValue('code', '');

        try {
            if (empty($code)) {
                throw new MoloniException('Code cannot be empty.');
            }

            MoloniApi::login($code);
        } catch (MoloniException $e) {
            $msg = $this->trans($e->getMessage(), 'Modules.Molonion.Errors', $e->getIdentifiers());
            $this->addErrorMessage($msg, $e->getData());

            return $this->redirectToLogin();
        }

        return $this->redirectToCompanySelect();
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
