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

use MoloniOn\Actions\ProductsList\Prestashop\FetchPrestashopProductsPaginated;
use MoloniOn\Actions\ProductsList\Prestashop\VerifyProductForList;
use MoloniOn\Controller\Admin\MoloniController;
use MoloniOn\Enums\MoloniRoutes;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Helpers\Warehouse;
use MoloniOn\MoloniContext;
use MoloniOn\Repository\ProductsRepository;
use MoloniOn\Services\MoloniProduct\Create\CreateSimpleProduct;
use MoloniOn\Services\MoloniProduct\Create\CreateVariantProduct;
use MoloniOn\Services\MoloniProduct\Helpers\FindMoloniProductByReference;
use MoloniOn\Services\MoloniProduct\Stock\SyncProductStock;
use MoloniOn\Tools\Settings;
use MoloniOn\Tools\SyncLogs;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PrestashopProducts extends MoloniController
{
    public function home(): Response
    {
        $page = (int) \Tools::getValue('page', 1);
        $filters = \Tools::getValue('filters', []);

        /** @var ProductsRepository $repository */
        $repository = $this->get('molonion.repository.products');

        $service = new FetchPrestashopProductsPaginated($page, $repository, $this->getContextLangId(), $this->getContextShopId());
        $service->setFilters($filters);
        $service->run();

        return $this->display(
            'products/prestashop/Products.twig',
            [
                'productsArray' => $service->getProducts(),
                'filters' => $filters,
                'paginator' => $service->getPaginator(),
                'companyName' => Settings::get('companyName'),
                'exportStockRoute' => MoloniRoutes::PRESTASHOP_PRODUCTS_EXPORT_STOCK,
                'exportProductRoute' => MoloniRoutes::PRESTASHOP_PRODUCTS_EXPORT_PRODUCT,
                'toolsRoute' => MoloniRoutes::TOOLS,
                'thisRoute' => MoloniRoutes::PRESTASHOP_PRODUCTS,
            ]
        );
    }

    public function exportStock(): Response
    {
        $productId = (int) \Tools::getValue('product_id', 0);

        SyncLogs::prestashopProductAddTimeout($productId);

        try {
            $product = new \Product($productId, true, \Configuration::get('PS_LANG_DEFAULT'));

            if (empty($product->id)) {
                throw new MoloniProductException('Product not found', null, [$productId]);
            }

            $isVariant = $product->product_type === 'combinations' && $product->hasCombinations();

            if ($isVariant && !MoloniContext::instance()->company()->hasProperties()) {
                throw new MoloniProductException('Product skipped: the Product Properties module is not active in your Moloni ON company.', null, [$productId]);
            }

            $moloniProduct = FindMoloniProductByReference::fromPrestashopProduct($product);

            if (!empty($moloniProduct)) {
                $service = new SyncProductStock($product, $moloniProduct);
                $service->run();
                $service->saveLog();
            } else {
                throw new MoloniProductException('Product does not exist in Moloni ON', null, [$productId]);
            }

            $response = $this->getCommonResponse($productId);
        } catch (MoloniProductException $e) {
            $response = [
                'valid' => 0,
                'message' => $this->trans($e->getMessage(), 'Modules.Molonion.Errors'),
                'result' => $e->getData(),
                'productRow' => '',
            ];
        }

        return new Response(json_encode($response));
    }

    public function exportProduct(): Response
    {
        $productId = (int) \Tools::getValue('product_id', 0);

        SyncLogs::prestashopProductAddTimeout($productId);

        try {
            $product = new \Product($productId, true, \Configuration::get('PS_LANG_DEFAULT'));

            if (empty($product->id)) {
                throw new MoloniProductException('Product not found', null, [$productId]);
            }

            $isVariant = $product->product_type === 'combinations' && $product->hasCombinations();

            if ($isVariant && !MoloniContext::instance()->company()->hasProperties()) {
                throw new MoloniProductException('Product skipped: the Product Properties module is not active in your Moloni ON company.', null, [$productId]);
            }

            $moloniProduct = FindMoloniProductByReference::fromPrestashopProduct($product);

            if (empty($moloniProduct)) {
                $service = $isVariant
                    ? new CreateVariantProduct($product)
                    : new CreateSimpleProduct($product);

                $service->run();
                $service->saveLog();
            } else {
                throw new MoloniProductException('Product already exists', null, [$productId]);
            }

            $response = $this->getCommonResponse($productId);
        } catch (MoloniProductException $e) {
            $response = [
                'valid' => 0,
                'message' => $this->trans($e->getMessage(), 'Modules.Molonion.Errors'),
                'result' => $e->getData(),
                'productRow' => '',
            ];
        }

        return new Response(json_encode($response));
    }

    private function getWarehouse(): int
    {
        if (!MoloniContext::instance()->company()->canSyncStock()) {
            return 0;
        }

        $warehouseId = (int) Settings::get('syncStockToMoloniWarehouse');

        if ($warehouseId > 1) {
            return $warehouseId;
        }

        return Warehouse::getCompanyDefaultWarehouse();
    }

    private function getCommonResponse($prestaProductId): array
    {
        $warehouseId = $this->getWarehouse();

        $ps = new \Product($prestaProductId, false, $this->getContextLangId());

        $service = new VerifyProductForList($ps, $warehouseId);
        $service->run();

        return [
            'valid' => 1,
            'message' => '',
            'result' => '',
            'productRow' => $this->displayView(
                'products/prestashop/blocks/TableBodyRow.twig',
                [
                    'product' => $service->getParsedProduct(),
                ]
            ),
        ];
    }
}
