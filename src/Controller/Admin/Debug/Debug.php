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

namespace MoloniOn\Controller\Admin\Debug;

use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Controller\Admin\MoloniController;
use MoloniOn\Entity\MoloniOnOrderDocuments;
use MoloniOn\Entity\MoloniOnProductAssociations;
use MoloniOn\Enums\MoloniRoutes;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Repository\MoloniOnOrderDocumentsRepository;
use MoloniOn\Services\MoloniProduct\Create\CreateSimpleProduct;
use MoloniOn\Services\MoloniProduct\Create\CreateVariantProduct;
use MoloniOn\Services\MoloniProduct\Helpers\FindMoloniProductByReference;
use MoloniOn\Services\MoloniProduct\Stock\SyncProductStock;
use MoloniOn\Services\MoloniProduct\Update\UpdateSimpleProduct;
use MoloniOn\Services\MoloniProduct\Update\UpdateVariantProduct;
use MoloniOn\Services\PrestashopProduct\Create\CreateCombinationsProduct;
use MoloniOn\Services\PrestashopProduct\Create\CreateSimpleProduct as PrestashopCreateSimpleProduct;
use MoloniOn\Services\PrestashopProduct\Helpers\FindPrestashopProductByReference;
use MoloniOn\Services\PrestashopProduct\Stock\SyncProductStock as PrestashopSyncProductStock;
use MoloniOn\Services\PrestashopProduct\Update\UpdateCombinationsProduct;
use MoloniOn\Services\PrestashopProduct\Update\UpdateSimpleProduct as PrestashopUpdateSimpleProduct;
use MoloniOn\Tools\ProductAssociations;
use MoloniOn\Tools\SyncLogs;
use Product;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Debug extends MoloniController
{
    /**
     * Home view for debug actions
     *
     * @param array|null $data Data
     *
     * @return Response
     */
    public function home(?array $data = []): Response
    {
        return $this->display(
            'debug/Debug.twig',
            [
                'multipurpose' => MoloniRoutes::DEBUG_MULTIPURPOSE,
                'deleteOrderDocument' => MoloniRoutes::DEBUG_DELETE_ORDER_DOCUMENT,
                'updateStockFromMoloni' => MoloniRoutes::DEBUG_UPDATE_STOCK_FROM_MOLONI,
                'updateProductFromMoloni' => MoloniRoutes::DEBUG_UPDATE_PRODUCT_FROM_MOLONI,
                'insertProductFromMoloni' => MoloniRoutes::DEBUG_INSERT_PRODUCT_FROM_MOLONI,
                'updateStockFromPrestashop' => MoloniRoutes::DEBUG_UPDATE_STOCK_FROM_PRESTASHOP,
                'updateProductFromPrestashop' => MoloniRoutes::DEBUG_UPDATE_PRODUCT_FROM_PRESTASHOP,
                'insertProductFromPrestashop' => MoloniRoutes::DEBUG_INSERT_PRODUCT_FROM_PRESTASHOP,
                'dumpProductAssociations' => MoloniRoutes::DEBUG_DUMP_PRODUCT_ASSOCIATIONS,
                'orders' => MoloniRoutes::ORDERS,
                'data' => $data,
            ]
        );
    }

    /**
     * Multipurpose action to debug user problems
     *
     * @return Response
     */
    public function multipurpose(): Response
    {
        $taxRulesGroupId = 0;

        $fiscalZone = 'es';
        $countryId = \Country::getByIso($fiscalZone);
        $value = 21.0;

        $taxes = array_reverse(\TaxRulesGroup::getAssociatedTaxRatesByIdCountry($countryId), true);

        foreach ($taxes as $id => $tax) {
            if ($value === (float) $tax) {
                $taxRulesGroupId = $id;

                break;
            }
        }

        $response = [
            'valid' => 1,
            'result' => [
                'fiscalZone' => $fiscalZone,
                'countryId' => $countryId,
                'value' => $value,
                'taxes' => $taxes,
                'taxRulesGroupId' => $taxRulesGroupId,
            ],
        ];

        return $this->home($response);
    }

    /**
     * Checks if attributes with all upper-case letters are being used
     *
     * @return Response
     */
    public function deleteOrderDocument(): Response
    {
        $orderId = (int) \Tools::getValue('order_id', 0);

        /** @var MoloniOnOrderDocumentsRepository $repository */
        $repository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository(MoloniOnOrderDocuments::class);

        $repository->deleteByOrderId($orderId);

        $response = [
            'valid' => 1,
            'result' => 'Done :)',
        ];

        return $this->home($response);
    }

    /**
     * Update prestashop product stock based on Moloni
     */
    public function updateStockFromMoloni(): Response
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

                $service = new PrestashopSyncProductStock($moloniProduct, $prestashopProduct);
                $service->run();
                $service->saveLog();
            }

            $response = [
                'valid' => 1,
                'result' => 'Done :)',
            ];
        } catch (MoloniProductException|MoloniApiException $e) {
            $response = [
                'valid' => 0,
                'message' => $e->getMessage(),
                'result' => $e->getData(),
            ];
        }

        return $this->home($response);
    }

    /**
     * Update prestashop product stock based on Moloni
     */
    public function updateProductFromMoloni(): Response
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

            if ($prestaProductId > 0) {
                SyncLogs::prestashopProductAddTimeout($prestaProductId);

                $service = $isCombinations
                    ? new UpdateCombinationsProduct($moloniProduct, $prestashopProduct)
                    : new PrestashopUpdateSimpleProduct($moloniProduct, $prestashopProduct);
                $service->run();
                $service->saveLog();
            } else {
                throw new MoloniProductException('Product does not exist', null, ['prestashopId' => $prestaProductId, 'moloniId' => $productId]);
            }

            $response = [
                'valid' => 1,
                'result' => 'Done :)',
            ];
        } catch (MoloniProductException|MoloniApiException $e) {
            $response = [
                'valid' => 0,
                'message' => $e->getMessage(),
                'result' => $e->getData(),
            ];
        }

        return $this->home($response);
    }

    /**
     * Update prestashop product stock based on Moloni
     */
    public function insertProductFromMoloni(): Response
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
                    : new PrestashopCreateSimpleProduct($moloniProduct, $prestashopProduct);
                $service->run();
                $service->saveLog();
            } else {
                throw new MoloniProductException('Product already exists', null, ['prestashopId' => $prestaProductId, 'moloniId' => $productId]);
            }

            $response = [
                'valid' => 1,
                'result' => 'Done :)',
            ];
        } catch (MoloniProductException|MoloniApiException $e) {
            $response = [
                'valid' => 0,
                'message' => $e->getMessage(),
                'result' => $e->getData(),
            ];
        }

        return $this->home($response);
    }

    /**
     * Update Moloni product stock based on prestashop
     */
    public function updateStockFromPrestashop(): Response
    {
        $productId = (int) \Tools::getValue('product_id', 0);

        SyncLogs::prestashopProductAddTimeout($productId);

        try {
            $product = new \Product($productId, true, \Configuration::get('PS_LANG_DEFAULT'));

            if (empty($product->id)) {
                throw new MoloniProductException('Product not found', null, [$productId]);
            }

            $moloniProduct = FindMoloniProductByReference::fromPrestashopProduct($product);

            if (!empty($moloniProduct)) {
                SyncLogs::moloniProductAddTimeout((int) $moloniProduct['productId']);

                $service = new SyncProductStock($product, $moloniProduct);
                $service->run();
                $service->saveLog();
            } else {
                throw new MoloniProductException('Product does not exist', null, [$productId]);
            }

            $response = [
                'valid' => 1,
                'result' => 'Done :)',
            ];
        } catch (MoloniProductException $e) {
            $response = [
                'valid' => 0,
                'message' => $e->getMessage(),
                'result' => $e->getData(),
            ];
        }

        return $this->home($response);
    }

    /**
     * Update Moloni product stock based on prestashop
     */
    public function updateProductFromPrestashop(): Response
    {
        $productId = (int) \Tools::getValue('product_id', 0);

        SyncLogs::prestashopProductAddTimeout($productId);

        try {
            $product = new \Product($productId, true, \Configuration::get('PS_LANG_DEFAULT'));

            if (empty($product->id)) {
                throw new MoloniProductException('Product not found', null, [$productId]);
            }

            $isVariant = $product->product_type === 'combinations' && $product->hasCombinations();

            $moloniProduct = FindMoloniProductByReference::fromPrestashopProduct($product);

            if (!empty($moloniProduct)) {
                SyncLogs::moloniProductAddTimeout((int) $moloniProduct['productId']);

                $service = $isVariant
                    ? new UpdateVariantProduct($product, $moloniProduct)
                    : new UpdateSimpleProduct($product, $moloniProduct);
                $service->run();
                $service->saveLog();
            } else {
                throw new MoloniProductException('Product does not exist', null, [$productId]);
            }

            $response = [
                'valid' => 1,
                'result' => 'Done :)',
            ];
        } catch (MoloniProductException $e) {
            $response = [
                'valid' => 0,
                'message' => $e->getMessage(),
                'result' => $e->getData(),
            ];
        }

        return $this->home($response);
    }

    /**
     * Update Moloni product stock based on prestashop
     */
    public function insertProductFromPrestashop(): Response
    {
        $productId = (int) \Tools::getValue('product_id', 0);

        SyncLogs::prestashopProductAddTimeout($productId);

        try {
            $product = new \Product($productId, true, \Configuration::get('PS_LANG_DEFAULT'));

            if (empty($product->id)) {
                throw new MoloniProductException('Product not found', null, [$productId]);
            }

            $isVariant = $product->product_type === 'combinations' && $product->hasCombinations();

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

            $response = [
                'valid' => 1,
                'result' => 'Done :)',
            ];
        } catch (MoloniProductException $e) {
            $response = [
                'valid' => 0,
                'message' => $e->getMessage(),
                'result' => $e->getData(),
            ];
        }

        return $this->home($response);
    }

    /**
     * Dump product associations table
     */
    public function dumpProductAssociations(): Response
    {
        $results = [];
        $productId = (int) \Tools::getValue('product_id', 0);
        $type = \Tools::getValue('type_id', '');

        switch ($type) {
            case 'MOLONI_PRODUCT':
                $result = ProductAssociations::findByMoloniParentId($productId);
                break;
            case 'MOLONI_VARIANT':
                $result = ProductAssociations::findByMoloniVariantId($productId);
                break;
            case 'PRESTASHOP_PRODUCT':
                $result = ProductAssociations::findByPrestashopProductId($productId);
                break;
            case 'PRESTASHOP_COMBINATION':
                $result = ProductAssociations::findByPrestashopCombinationId($productId);
                break;
            case 'ALL':
            default:
                $result = ProductAssociations::findAll();
                break;
        }

        /** @var MoloniOnProductAssociations[] $result */
        foreach ($result as $association) {
            $results[] = $association->toArray();
        }

        $response = [
            'valid' => 1,
            'result' => json_encode($results),
        ];

        return $this->home($response);
    }
}
