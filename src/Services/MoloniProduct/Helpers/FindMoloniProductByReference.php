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
        return (new self(ProductReference::fromPrestashopProduct($prestashopProduct)))->handle();
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
