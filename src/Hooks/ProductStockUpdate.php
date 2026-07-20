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

namespace MoloniOn\Hooks;

use MoloniOn\Api\MoloniApi;
use MoloniOn\Entity\MoloniOnProductAssociations;
use MoloniOn\Enums\Boolean;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\MoloniContext;
use MoloniOn\Services\MoloniProduct\Helpers\FindMoloniProductById;
use MoloniOn\Services\MoloniProduct\Helpers\FindMoloniProductByReference;
use MoloniOn\Services\MoloniProduct\Stock\SyncProductStock;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\ProductAssociations;
use MoloniOn\Tools\Settings;
use MoloniOn\Tools\SyncLogs;
use Product;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductStockUpdate extends AbstractHookAction
{
    private $productId;
    private $variantId;
    private $newQty;

    public function __construct(int $productId, int $variantId, float $newQty)
    {
        $this->productId = $productId;
        $this->variantId = $variantId;
        $this->newQty = $newQty;

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

            if ($isVariant && !$this->variantId) {
                return;
            }

            if ($isVariant && !MoloniContext::instance()->company()->hasProperties()) {
                /* No Product Properties module: the combination is a standalone simple product in Moloni */
                $this->syncCombinationAsSimpleStock($product);

                return;
            }

            $moloniProduct = FindMoloniProductByReference::fromPrestashopProduct($product);

            if (empty($moloniProduct)) {
                return;
            }

            $service = new SyncProductStock(
                $product,
                $moloniProduct,
                $isVariant ? $this->variantId : 0,
                $this->newQty
            );

            $service->run();
            $service->saveLog();
        } catch (MoloniProductException $e) {
            Logs::addErrorLog(
                [['Error saving Moloni ON product'], [$e->getMessage(), $e->getIdentifiers()]],
                $e->getData()
            );
        }
    }

    /**
     * Company has no Product Properties module: the combination is a standalone
     * simple product in Moloni. Sync its stock through the stored association
     * (created when the combination was invoiced); do nothing when it was never
     * created.
     *
     * @param \Product $product
     *
     * @return void
     *
     * @throws MoloniProductException
     */
    private function syncCombinationAsSimpleStock(\Product $product): void
    {
        /** @var MoloniOnProductAssociations|null $association */
        $association = ProductAssociations::findByPrestashopCombinationId($this->variantId);

        if ($association === null || $association->getMlVariantId() > 0 || $association->getMlProductId() <= 0) {
            return;
        }

        $moloniProduct = FindMoloniProductById::handle($association->getMlProductId());

        if (empty($moloniProduct)) {
            return;
        }

        $service = new SyncProductStock($product, $moloniProduct, 0, $this->newQty);
        $service->run();
        $service->saveLog();
    }

    /**
     * Let this conditions be the same to allow for updates or inserts if we are inserting or updating a product
     */
    private function shouldExecuteHandle(): bool
    {
        if ($this->productId < 1) {
            return false;
        }

        if ((int) Settings::get('addProductsToMoloni') === Boolean::NO
            && (int) Settings::get('updateProductsToMoloni') === Boolean::NO) {
            return false;
        }

        if ((int) Settings::get('syncStockToMoloni') === Boolean::NO) {
            return false;
        }

        if (SyncLogs::prestashopProductHasTimeout($this->productId)) {
            return false;
        }

        if (!MoloniApi::hasAuthenticationAndCompany()) {
            return false;
        }

        return MoloniContext::instance()->company()->canSyncStock();
    }
}
