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

namespace MoloniOn\Builders\Document\Helpers;

use MoloniOn\Exceptions\MoloniException;
use MoloniOn\Services\Tax\TaxFromRate;
use Tax;

if (!defined('_PS_VERSION_')) {
    exit;
}

class GetOrderProductTax
{
    private $orderProduct;
    private $fiscalZone;

    /**
     * Construct
     *
     * @param array $orderProduct
     * @param array $fiscalZone
     */
    public function __construct(array $orderProduct, array $fiscalZone)
    {
        $this->orderProduct = $orderProduct;
        $this->fiscalZone = $fiscalZone;
    }

    /**
     * Handler
     *
     * @return TaxFromRate|null
     *
     * @throws MoloniException
     */
    public function handle(): ?TaxFromRate
    {
        $taxValue = (float) ($this->orderProduct['tax_rate'] ?? 0);

        if ($taxValue > 0) {
            $taxBuilder = new TaxFromRate($taxValue, $this->fiscalZone, 1);
            $taxBuilder->getOrCreate();

            return $taxBuilder;
        }

        /* Fallback tax percentage calculation */
        if ($this->orderProduct['unit_price_tax_incl'] !== $this->orderProduct['unit_price_tax_excl']) {
            $taxValue = (100 * ((float) $this->orderProduct['unit_price_tax_incl'] - (float) $this->orderProduct['unit_price_tax_excl'])) / (float) $this->orderProduct['unit_price_tax_excl'];

            $roundOne = round($taxValue, 1);

            $taxBuilder = new TaxFromRate($roundOne, $this->fiscalZone, 1);
            $taxBuilder->search();

            if ($taxBuilder->getTaxId() > 0) {
                return $taxBuilder;
            }

            $roundZero = round($taxValue, 0);

            $taxBuilder = new TaxFromRate($roundZero, $this->fiscalZone, 1);
            $taxBuilder->search();

            if ($taxBuilder->getTaxId() > 0) {
                return $taxBuilder;
            }

            $message = 'Tax not found in Moloni ON. Please create the correct tax for {0} ({1} || {2})';
            $identifiers = [
                '{0}' => $this->fiscalZone['code'],
                '{1}' => $roundOne,
                '{2}' => $roundZero,
            ];
            $data = [
                'original' => $taxValue,
                'round_one' => $roundOne,
                'round_zero' => $roundZero,
            ];

            throw new MoloniException($message, $identifiers, $data);
        }

        return null;
    }
}
