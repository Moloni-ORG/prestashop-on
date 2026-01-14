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

namespace MoloniOn\Controller\Admin\Tools;

use MoloniOn\Actions\Exports\ExportProductsToMoloni;
use MoloniOn\Actions\Exports\ExportStocksToMoloni;
use MoloniOn\Actions\Imports\ImportProductsFromMoloni;
use MoloniOn\Actions\Imports\ImportStockChangesFromMoloni;
use MoloniOn\Actions\Tools\WebhookCreate;
use MoloniOn\Actions\Tools\WebhookDeleteAll;
use MoloniOn\Controller\Admin\MoloniController;
use MoloniOn\Enums\Boolean;
use MoloniOn\Enums\MoloniRoutes;
use MoloniOn\Exceptions\MoloniException;
use MoloniOn\Tools\Settings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Tools as PrestashopTools;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Tools extends MoloniController
{
    public function home(): Response
    {
        return $this->display(
            'tools/Tools.twig',
            [
                'companyName' => Settings::get('companyName'),
                'importProductsRoute' => MoloniRoutes::TOOLS_IMPORT_PRODUCTS,
                'importStocksRoute' => MoloniRoutes::TOOLS_IMPORT_STOCKS,
                'exportProductsRoute' => MoloniRoutes::TOOLS_EXPORT_PRODUCTS,
                'exportStocksRoute' => MoloniRoutes::TOOLS_EXPORT_STOCKS,
                'reinstallHooksRoute' => MoloniRoutes::TOOLS_REINSTALL_HOOKS,
                'productsPrestashopRoutes' => MoloniRoutes::PRESTASHOP_PRODUCTS,
                'productsMoloniRoutes' => MoloniRoutes::MOLONI_PRODUCTS,
                'logoutRoute' => MoloniRoutes::TOOLS_LOGOUT,
                'company' => $this->moloniContext->company(),
            ]
        );
    }

    public function importProducts(): Response
    {
        $page = (int) PrestashopTools::getValue('page', 1);

        $response = [
            'valid' => true,
            'post' => [
                'page' => $page,
            ],
        ];

        $tool = new ImportProductsFromMoloni($page);
        $tool->handle();

        $response['hasMore'] = $tool->getHasMore();

        $response['overlayContent'] = $this->displayView(
            'tools/overlays/blocks/ProductImportContent.twig',
            [
                'hasMore' => $tool->getHasMore(),
                'totalResults' => $tool->getTotalResults(),
                'currentPercentage' => $tool->getCurrentPercentage(),
            ]
        );

        return new Response(json_encode($response));
    }

    public function importStocks(): Response
    {
        $page = (int) PrestashopTools::getValue('page', 1);

        $response = [
            'valid' => true,
            'post' => [
                'page' => $page,
            ],
        ];

        $tool = new ImportStockChangesFromMoloni($page);
        $tool->handle();

        $response['hasMore'] = $tool->getHasMore();

        $response['overlayContent'] = $this->displayView(
            'tools/overlays/blocks/ProductImportContent.twig',
            [
                'hasMore' => $tool->getHasMore(),
                'totalResults' => $tool->getTotalResults(),
                'currentPercentage' => $tool->getCurrentPercentage(),
            ]
        );

        return new Response(json_encode($response));
    }

    public function exportProducts(): Response
    {
        $page = (int) PrestashopTools::getValue('page', 1);

        $response = [
            'valid' => true,
            'post' => [
                'page' => $page,
            ],
        ];

        $tool = new ExportProductsToMoloni($page, $this->getContextLangId());
        $tool->handle();

        $response['hasMore'] = $tool->getHasMore();

        $response['overlayContent'] = $this->displayView(
            'tools/overlays/blocks/ProductExportContent.twig',
            [
                'hasMore' => $tool->getHasMore(),
                'processedProducts' => $tool->getProcessedProducts(),
            ]
        );

        return new Response(json_encode($response));
    }

    public function exportStocks(): Response
    {
        $page = (int) PrestashopTools::getValue('page', 1);

        $response = [
            'valid' => true,
            'post' => [
                'page' => $page,
            ],
        ];

        $tool = new ExportStocksToMoloni($page, $this->getContextLangId());
        $tool->handle();

        $response['hasMore'] = $tool->getHasMore();

        $response['overlayContent'] = $this->displayView(
            'tools/overlays/blocks/ProductExportContent.twig',
            [
                'hasMore' => $tool->getHasMore(),
                'processedProducts' => $tool->getProcessedProducts(),
            ]
        );

        return new Response(json_encode($response));
    }

    public function reinstallHooks(): RedirectResponse
    {
        try {
            (new WebhookDeleteAll())->handle();
            $action = new WebhookCreate();

            if ((int) Settings::get('syncStockToPrestashop') === Boolean::YES) {
                $action->handle('Product', 'stockChanged');
            }

            if ((int) Settings::get('addProductsToPrestashop') === Boolean::YES) {
                $action->handle('Product', 'create');
            }

            if ((int) Settings::get('updateProductsToPrestashop') === Boolean::YES) {
                $action->handle('Product', 'update');
            }

            $msg = $this->trans('Webhooks reinstall was successful', 'Modules.Molonion.Common');
            $this->addSuccessMessage($msg);
        } catch (MoloniException $e) {
            $msg = $this->trans($e->getMessage(), 'Modules.Molonion.Errors', $e->getIdentifiers());
            $this->addErrorMessage($msg, $e->getData());
        }

        return $this->redirectToTools();
    }

    public function logout(): RedirectResponse
    {
        if (empty($this->moloniContext->getCompanyId())) {
            return $this->redirectToLogin();
        }

        $company = $this->moloniContext->company();

        if ($company && $company->canSyncStock()) {
            try {
                (new WebhookDeleteAll())->handle();
            } catch (MoloniException $e) {
                // catch nothing
            }
        }

        return $this->redirectToLogin();
    }
}
