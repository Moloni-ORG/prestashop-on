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

namespace MoloniOn\Hooks;

use MoloniOn\Api\MoloniApi;
use MoloniOn\Enums\Boolean;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\MoloniContext;
use MoloniOn\Services\MoloniProduct\Create\CreateSimpleProduct;
use MoloniOn\Services\MoloniProduct\Create\CreateVariantProduct;
use MoloniOn\Services\MoloniProduct\Helpers\FindMoloniProductByReference;
use MoloniOn\Services\MoloniProduct\Update\UpdateSimpleProduct;
use MoloniOn\Services\MoloniProduct\Update\UpdateVariantProduct;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\Settings;
use MoloniOn\Tools\SyncLogs;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductSave extends AbstractHookAction
{
    private $productId;

    public function __construct(int $productId)
    {
        $this->productId = $productId;

        $this->handle();
    }

    private function handle(): void
    {
        if (!$this->shouldExecuteHandle()) {
            return;
        }

        try {
            SyncLogs::prestashopProductAddTimeout($this->productId);
            $product = new \Product($this->productId, true, \Configuration::get('PS_LANG_DEFAULT'));

            $isVariant = $product->product_type === 'combinations' && $product->hasCombinations();

            if ($isVariant && !MoloniContext::instance()->company()->hasProperties()) {
                Logs::addWarningLog(
                    'Product with combinations not synced to Moloni ON: the Product Properties module is not active in your Moloni ON company.',
                    ['module' => 'productsServices.productProperties']
                );

                return;
            }

            $moloniProduct = FindMoloniProductByReference::fromPrestashopProduct($product);

            if (!empty($moloniProduct)) {
                SyncLogs::moloniProductAddTimeout((int) $moloniProduct['productId']);

                if ((int) Settings::get('updateProductsToMoloni') === Boolean::YES) {
                    $service = $isVariant
                        ? new UpdateVariantProduct($product, $moloniProduct)
                        : new UpdateSimpleProduct($product, $moloniProduct);

                    $service->run();
                    $service->saveLog();
                }
            } elseif ((int) Settings::get('addProductsToMoloni') === Boolean::YES) {
                $service = $isVariant
                    ? new CreateVariantProduct($product)
                    : new CreateSimpleProduct($product);

                $service->run();
                $service->saveLog();
            }
        } catch (MoloniProductException $e) {
            Logs::addErrorLog(
                [['Error saving Moloni ON product'], [$e->getMessage(), $e->getIdentifiers()]],
                $e->getData()
            );
        }
    }

    private function shouldExecuteHandle(): bool
    {
        if ($this->productId < 1) {
            return false;
        }

        if ((int) Settings::get('addProductsToMoloni') === Boolean::NO
            && (int) Settings::get('updateProductsToMoloni') === Boolean::NO) {
            return false;
        }

        if (SyncLogs::prestashopProductHasTimeout($this->productId)) {
            return false;
        }

        return MoloniApi::hasAuthenticationAndCompany();
    }
}
