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

namespace MoloniOn\Services\MoloniProduct\Helpers;

use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\Exceptions\Product\MoloniProductException;
use MoloniOn\Tools\ProductAssociations;
use MoloniOn\Traits\MoloniProductReferenceTrait;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Fetches a Moloni product by reference.
 *
 * Extracted from the old product builders so orchestrators can decide between a
 * create and an update service before instantiating one.
 */
class FindMoloniProductByReference
{
    use MoloniProductReferenceTrait;

    /**
     * @var string
     */
    private $reference;

    public function __construct(string $reference)
    {
        $this->reference = $reference;
    }

    /**
     * Build the finder from a PrestaShop product, using the same reference
     * fallback the builders use (product reference, or its id when empty).
     *
     * @param \Product $prestashopProduct
     *
     * @return array Matched Moloni product, or empty array when not found
     *
     * @throws MoloniProductException
     */
    public static function fromPrestashopProduct(\Product $prestashopProduct): array
    {
        /* Prefer a stored simple mapping: matches even after the reference changed */
        $association = ProductAssociations::findSimpleByPrestashopProductId((int) $prestashopProduct->id);

        if ($association !== null) {
            $moloniProduct = self::byId((int) $association->getMlProductId());

            if (!empty($moloniProduct)) {
                return $moloniProduct;
            }
        }

        return (new self(ProductReference::fromPrestashopProduct($prestashopProduct)))->handle();
    }

    /**
     * Fetch a Moloni product by its id.
     *
     * Returns an empty array when the product no longer exists, so callers fall
     * back to a reference search instead of trusting a stale mapping.
     *
     * @param int $moloniProductId
     *
     * @return array
     */
    public static function byId(int $moloniProductId): array
    {
        if ($moloniProductId <= 0) {
            return [];
        }

        try {
            $query = MoloniApiClient::products()
                ->queryProduct(['productId' => $moloniProductId]);
        } catch (MoloniApiException $e) {
            return [];
        }

        return $query['data']['product']['data'] ?? [];
    }

    /**
     * Run the search.
     *
     * @return array Matched Moloni product, or empty array when not found
     *
     * @throws MoloniProductException
     */
    public function handle(): array
    {
        $variables = [
            'options' => [
                'filter' => [
                    [
                        'field' => 'reference',
                        'comparison' => 'eq',
                        'value' => $this->reference,
                    ],
                ],
            ],
        ];

        try {
            $query = MoloniApiClient::products()
                ->queryProducts($variables);
        } catch (MoloniApiException $e) {
            throw new MoloniProductException('Error fetching product by reference: ({0})', ['{0}' => $this->reference], $e->getData());
        }

        return $this->findExactReferenceMatch($query, $this->reference);
    }
}
