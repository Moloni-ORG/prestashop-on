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

namespace MoloniOn\Controller\Admin\Products;

use MoloniOn\Actions\ProductsList\Moloni\FetchMoloniProductsPaginated;
use MoloniOn\Actions\ProductsList\Moloni\VerifyProductForList;
use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Controller\Admin\MoloniController;
use MoloniOn\Enums\MoloniRoutes;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Services\PrestashopProduct\Create\CreateCombinationsProduct;
use MoloniOn\Services\PrestashopProduct\Create\CreateSimpleProduct;
use MoloniOn\Services\PrestashopProduct\Helpers\FindPrestashopProductByReference;
use MoloniOn\Services\PrestashopProduct\Stock\SyncProductStock;
use MoloniOn\Tools\Settings;
use MoloniOn\Tools\SyncLogs;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

class MoloniProducts extends MoloniController
{
    public function home(): Response
    {
        $page = (int) \Tools::getValue('page', 1);
        $filters = \Tools::getValue('filters', []);

        $service = new FetchMoloniProductsPaginated($page, $filters);
        $service->run();

        return $this->display(
            'products/moloni/Products.twig',
            [
                'productsArray' => $service->getProducts(),
                'filters' => $filters,
                'paginator' => $service->getPaginator(),
                'companyName' => Settings::get('companyName'),
                'productReferenceFallbackActive' => (int) Settings::get('productReferenceFallback'),
                'importStockRoute' => MoloniRoutes::MOLONI_PRODUCTS_IMPORT_STOCK,
                'importProductRoute' => MoloniRoutes::MOLONI_PRODUCTS_IMPORT_PRODUCT,
                'toolsRoute' => MoloniRoutes::TOOLS,
                'thisRoute' => MoloniRoutes::MOLONI_PRODUCTS,
            ]
        );
    }

    public function importStock(): Response
    {
        $productId = (int) \Tools::getValue('product_id', 0);

        $variables = [
            'productId' => $productId,
        ];

        SyncLogs::moloniProductAddTimeout($productId);

        try {
            $query = MoloniApiClient::products()->queryProduct($variables);

            $moloniProduct = $query['data']['product']['data'] ?? [];

            if (empty($moloniProduct)) {
                throw new MoloniProductException('Product not found', null, $variables);
            }

            $prestashopProduct = FindPrestashopProductByReference::fromMoloniProduct($moloniProduct);
            $prestaProductId = (int) $prestashopProduct->id;

            if ($prestaProductId > 0) {
                SyncLogs::prestashopProductAddTimeout($prestaProductId);

                $service = new SyncProductStock($moloniProduct, $prestashopProduct);
                $service->run();
                $service->saveLog();
            } else {
                throw new MoloniProductException('Product does not exist', null, [$productId]);
            }

            $response = $this->getCommonResponse($moloniProduct);
        } catch (MoloniProductException|MoloniApiException $e) {
            $response = [
                'valid' => 0,
                'message' => $this->trans($e->getMessage(), 'Modules.Molonion.Errors'),
                'result' => $e->getData(),
                'productRow' => '',
            ];
        }

        return new Response(json_encode($response));
    }

    public function importProduct(): Response
    {
        $productId = (int) \Tools::getValue('product_id', 0);

        $variables = [
            'productId' => $productId,
        ];

        SyncLogs::moloniProductAddTimeout($productId);

        try {
            $query = MoloniApiClient::products()->queryProduct($variables);

            $moloniProduct = $query['data']['product']['data'] ?? [];

            if (empty($moloniProduct)) {
                throw new MoloniProductException('Product not found', null, $variables);
            }

            $isCombinations = !empty($moloniProduct['variants']);

            $prestashopProduct = FindPrestashopProductByReference::fromMoloniProduct($moloniProduct);
            $prestaProductId = (int) $prestashopProduct->id;

            if ($prestaProductId === 0) {
                $service = $isCombinations
                    ? new CreateCombinationsProduct($moloniProduct, $prestashopProduct)
                    : new CreateSimpleProduct($moloniProduct, $prestashopProduct);
                $service->run();
                $service->saveLog();
            } else {
                throw new MoloniProductException('Product already exists', null, ['prestashopId' => $prestaProductId, 'moloniId' => $productId]);
            }

            $response = $this->getCommonResponse($moloniProduct);
        } catch (MoloniProductException|MoloniApiException $e) {
            $response = [
                'valid' => 0,
                'message' => $this->trans($e->getMessage(), 'Modules.Molonion.Errors'),
                'result' => $e->getData(),
                'productRow' => '',
            ];
        }

        return new Response(json_encode($response));
    }

    private function getCommonResponse(array $moloniProduct): array
    {
        $warehouseId = (int) Settings::get('syncStockToPrestashopWarehouse');

        $service = new VerifyProductForList($moloniProduct, $warehouseId);
        $service->run();

        return [
            'valid' => 1,
            'message' => '',
            'result' => '',
            'productRow' => $this->displayView(
                'products/moloni/blocks/TableBodyRow.twig',
                [
                    'product' => $service->getParsedProduct(),
                ]
            ),
        ];
    }
}
