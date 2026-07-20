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
use MoloniOn\Entity\MoloniOnProductAssociations;
use MoloniOn\Enums\Boolean;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\MoloniContext;
use MoloniOn\Services\MoloniProduct\Create\CreateSimpleProduct;
use MoloniOn\Services\MoloniProduct\Create\CreateVariantProduct;
use MoloniOn\Services\MoloniProduct\Helpers\FindMoloniProductById;
use MoloniOn\Services\MoloniProduct\Helpers\FindMoloniProductByReference;
use MoloniOn\Services\MoloniProduct\Update\UpdateSimpleProduct;
use MoloniOn\Services\MoloniProduct\Update\UpdateSimpleProductFromCombination;
use MoloniOn\Services\MoloniProduct\Update\UpdateVariantProduct;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\ProductAssociations;
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
                /* No Product Properties module: combinations live in Moloni as simple products (created when invoiced) */
                $this->updateCombinationsAsSimple($product);

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

    /**
     * Company has no Product Properties module: each combination lives in Moloni
     * as a standalone simple product, created on demand when invoiced. Here we
     * only update the ones that already exist (never create new ones).
     *
     * @param \Product $product
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    private function updateCombinationsAsSimple(\Product $product): void
    {
        if ((int) Settings::get('updateProductsToMoloni') === Boolean::NO) {
            return;
        }

        $combinations = $product->getAttributeCombinations(null, false);

        $handled = [];

        foreach ($combinations as $combinationRow) {
            $combinationId = (int) $combinationRow['id_product_attribute'];

            if (isset($handled[$combinationId])) {
                continue;
            }

            $handled[$combinationId] = true;

            /** @var MoloniOnProductAssociations|null $association */
            $association = ProductAssociations::findByPrestashopCombinationId($combinationId);

            if ($association === null || $association->getMlVariantId() > 0 || $association->getMlProductId() <= 0) {
                continue;
            }

            $moloniProduct = FindMoloniProductById::handle($association->getMlProductId());

            if (empty($moloniProduct)) {
                continue;
            }

            SyncLogs::moloniProductAddTimeout((int) $moloniProduct['productId']);

            $combination = new \Combination($combinationId);

            $service = new UpdateSimpleProductFromCombination($product, $combination, $moloniProduct);
            $service->run();
            $service->saveLog();
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
