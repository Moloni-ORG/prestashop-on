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

namespace MoloniOn\Api;

use MoloniOn\Api\Endpoints\BusinessAreas;
use MoloniOn\Api\Endpoints\Categories;
use MoloniOn\Api\Endpoints\Companies;
use MoloniOn\Api\Endpoints\Countries;
use MoloniOn\Api\Endpoints\Currencies;
use MoloniOn\Api\Endpoints\Customers;
use MoloniOn\Api\Endpoints\DeliveryMethods;
use MoloniOn\Api\Endpoints\Documents\BillsOfLading;
use MoloniOn\Api\Endpoints\Documents\CreditNote;
use MoloniOn\Api\Endpoints\Documents\Estimate;
use MoloniOn\Api\Endpoints\Documents\Invoice;
use MoloniOn\Api\Endpoints\Documents\ProFormaInvoice;
use MoloniOn\Api\Endpoints\Documents\PurchaseOrder;
use MoloniOn\Api\Endpoints\Documents\Receipt;
use MoloniOn\Api\Endpoints\Documents\SimplifiedInvoice;
use MoloniOn\Api\Endpoints\DocumentSets;
use MoloniOn\Api\Endpoints\FiscalZone;
use MoloniOn\Api\Endpoints\GeographicZones;
use MoloniOn\Api\Endpoints\Hooks;
use MoloniOn\Api\Endpoints\Languages;
use MoloniOn\Api\Endpoints\MaturityDates;
use MoloniOn\Api\Endpoints\MeasurementUnits;
use MoloniOn\Api\Endpoints\PaymentMethods;
use MoloniOn\Api\Endpoints\PriceClasses;
use MoloniOn\Api\Endpoints\Products;
use MoloniOn\Api\Endpoints\PropertyGroups;
use MoloniOn\Api\Endpoints\Stock;
use MoloniOn\Api\Endpoints\Taxes;
use MoloniOn\Api\Endpoints\Timezones;
use MoloniOn\Api\Endpoints\Warehouses;

if (!defined('_PS_VERSION_')) {
    exit;
}

class MoloniApiClient
{
    //         Documents         //

    /**
     * @var BillsOfLading|null
     */
    private static $billsOfLading;
    /**
     * @var CreditNote|null
     */
    private static $creditNote;
    /**
     * @var Estimate|null
     */
    private static $estimate;
    /**
     * @var Invoice|null
     */
    private static $invoice;
    /**
     * @var ProFormaInvoice|null
     */
    private static $proFormaInvoice;
    /**
     * @var PurchaseOrder|null
     */
    private static $purchaseOrder;
    /**
     * @var Receipt|null
     */
    private static $receipt;
    /**
     * @var SimplifiedInvoice|null
     */
    private static $simplifiedInvoice;

    //         Generic         //

    /**
     * @var Categories|null
     */
    private static $categories;
    /**
     * @var Warehouses|null
     */
    private static $warehouses;
    /**
     * @var Companies|null
     */
    private static $companies;
    /**
     * @var Countries|null
     */
    private static $countries;
    /**
     * @var Currencies|null
     */
    private static $currencies;
    /**
     * @var Customers|null
     */
    private static $customers;
    /**
     * @var DeliveryMethods|null
     */
    private static $deliveryMethods;
    /**
     * @var DocumentSets|null
     */
    private static $documentSets;
    /**
     * @var FiscalZone|null
     */
    private static $fiscalZone;
    /**
     * @var BusinessAreas|null
     */
    private static $businessAreas;
    /**
     * @var GeographicZones|null
     */
    private static $geographicZones;
    /**
     * @var Hooks|null
     */
    private static $hooks;
    /**
     * @var Languages|null
     */
    private static $languages;
    /**
     * @var MaturityDates|null
     */
    private static $maturityDates;
    /**
     * @var MeasurementUnits|null
     */
    private static $measurementUnits;
    /**
     * @var PaymentMethods|null
     */
    private static $paymentMethods;
    /**
     * @var PriceClasses|null
     */
    private static $priceClasses;
    /**
     * @var Stock|null
     */
    private static $stock;
    /**
     * @var Timezones|null
     */
    private static $timezones;
    /**
     * @var Taxes|null
     */
    private static $taxes;
    /**
     * @var Products|null
     */
    private static $products;
    /**
     * @var PropertyGroups|null
     */
    private static $propertyGroups;

    //         Documents         //

    public static function billsOfLading(): BillsOfLading
    {
        if (!self::$billsOfLading) {
            self::$billsOfLading = new BillsOfLading();
        }

        return self::$billsOfLading;
    }

    public static function creditNote(): CreditNote
    {
        if (!self::$creditNote) {
            self::$creditNote = new CreditNote();
        }

        return self::$creditNote;
    }

    public static function estimate(): Estimate
    {
        if (!self::$estimate) {
            self::$estimate = new Estimate();
        }

        return self::$estimate;
    }

    public static function invoice(): Invoice
    {
        if (!self::$invoice) {
            self::$invoice = new Invoice();
        }

        return self::$invoice;
    }

    public static function proFormaInvoice(): ProFormaInvoice
    {
        if (!self::$proFormaInvoice) {
            self::$proFormaInvoice = new ProFormaInvoice();
        }

        return self::$proFormaInvoice;
    }

    public static function purchaseOrder(): PurchaseOrder
    {
        if (!self::$purchaseOrder) {
            self::$purchaseOrder = new PurchaseOrder();
        }

        return self::$purchaseOrder;
    }

    public static function receipt(): Receipt
    {
        if (!self::$receipt) {
            self::$receipt = new Receipt();
        }

        return self::$receipt;
    }

    public static function simplifiedInvoice(): SimplifiedInvoice
    {
        if (!self::$simplifiedInvoice) {
            self::$simplifiedInvoice = new SimplifiedInvoice();
        }

        return self::$simplifiedInvoice;
    }

    //         Generic         //

    public static function categories(): Categories
    {
        if (!self::$categories) {
            self::$categories = new Categories();
        }

        return self::$categories;
    }

    public static function companies(): Companies
    {
        if (!self::$companies) {
            self::$companies = new Companies();
        }

        return self::$companies;
    }

    public static function countries(): Countries
    {
        if (!self::$countries) {
            self::$countries = new Countries();
        }

        return self::$countries;
    }

    public static function currencies(): Currencies
    {
        if (!self::$currencies) {
            self::$currencies = new Currencies();
        }

        return self::$currencies;
    }

    public static function customers(): Customers
    {
        if (!self::$customers) {
            self::$customers = new Customers();
        }

        return self::$customers;
    }

    public static function deliveryMethods(): DeliveryMethods
    {
        if (!self::$deliveryMethods) {
            self::$deliveryMethods = new DeliveryMethods();
        }

        return self::$deliveryMethods;
    }

    public static function documentSets(): DocumentSets
    {
        if (!self::$documentSets) {
            self::$documentSets = new DocumentSets();
        }

        return self::$documentSets;
    }

    public static function fiscalZone(): FiscalZone
    {
        if (!self::$fiscalZone) {
            self::$fiscalZone = new FiscalZone();
        }

        return self::$fiscalZone;
    }

    public static function businessAreas(): BusinessAreas
    {
        if (!self::$businessAreas) {
            self::$businessAreas = new BusinessAreas();
        }

        return self::$businessAreas;
    }

    public static function geographicZones(): GeographicZones
    {
        if (!self::$geographicZones) {
            self::$geographicZones = new GeographicZones();
        }

        return self::$geographicZones;
    }

    public static function hooks(): Hooks
    {
        if (!self::$hooks) {
            self::$hooks = new Hooks();
        }

        return self::$hooks;
    }

    public static function languages(): Languages
    {
        if (!self::$languages) {
            self::$languages = new Languages();
        }

        return self::$languages;
    }

    public static function maturityDates(): MaturityDates
    {
        if (!self::$maturityDates) {
            self::$maturityDates = new MaturityDates();
        }

        return self::$maturityDates;
    }

    public static function measurementUnits(): MeasurementUnits
    {
        if (!self::$measurementUnits) {
            self::$measurementUnits = new MeasurementUnits();
        }

        return self::$measurementUnits;
    }

    public static function paymentMethods(): PaymentMethods
    {
        if (!self::$paymentMethods) {
            self::$paymentMethods = new PaymentMethods();
        }

        return self::$paymentMethods;
    }

    public static function priceClasses(): PriceClasses
    {
        if (!self::$priceClasses) {
            self::$priceClasses = new PriceClasses();
        }

        return self::$priceClasses;
    }

    public static function products(): Products
    {
        if (!self::$products) {
            self::$products = new Products();
        }

        return self::$products;
    }

    public static function propertyGroups(): PropertyGroups
    {
        if (!self::$propertyGroups) {
            self::$propertyGroups = new PropertyGroups();
        }

        return self::$propertyGroups;
    }

    public static function stock(): Stock
    {
        if (!self::$stock) {
            self::$stock = new Stock();
        }

        return self::$stock;
    }

    public static function taxes(): Taxes
    {
        if (!self::$taxes) {
            self::$taxes = new Taxes();
        }

        return self::$taxes;
    }

    public static function timezones(): Timezones
    {
        if (self::$timezones) {
            self::$timezones = new Timezones();
        }

        return self::$timezones;
    }

    public static function warehouses(): Warehouses
    {
        if (!self::$warehouses) {
            self::$warehouses = new Warehouses();
        }

        return self::$warehouses;
    }
}
