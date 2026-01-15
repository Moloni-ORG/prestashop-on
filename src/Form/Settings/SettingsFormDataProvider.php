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

namespace MoloniOn\Form\Settings;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use MoloniOn\Api\MoloniApiClient;
use MoloniOn\Context\Company;
use MoloniOn\Entity\MoloniOnSettings;
use MoloniOn\Enums\Boolean;
use MoloniOn\Enums\DocumentReference;
use MoloniOn\Enums\DocumentStatus;
use MoloniOn\Enums\DocumentTypes;
use MoloniOn\Enums\FiscalZone;
use MoloniOn\Enums\Languages;
use MoloniOn\Enums\LoadAddress;
use MoloniOn\Enums\ProductInformation;
use MoloniOn\Enums\SyncFields;
use MoloniOn\Exceptions\MoloniApiException;
use MoloniOn\MoloniContext;
use MoloniOn\Tools\Settings;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SettingsFormDataProvider implements FormDataProviderInterface
{
    private $translator;
    private $languageId;

    /** @var Company */
    private $company;

    private $settingsRepository;

    private $measurementUnits = [];
    private $stores = [];
    private $warehouses = [];
    private $documentSets = [];
    private $documentReference = [];
    private $countries = [];
    private $orderStatus = [];
    private $yesNo = [];
    private $productInformation;
    private $status;
    private $documentTypes = [];
    private $fiscalZoneBasedOn = [];
    private $syncFields = [];
    private $addresses = [];
    private $customerLanguage = [];
    private $exemptionReasons = [];
    private $companyName = '';

    public function __construct(MoloniContext $context, int $languageId)
    {
        $this->translator = $context->iTranslator();
        $this->settingsRepository = $context->iEntityManager()->getRepository(MoloniOnSettings::class);

        $this->languageId = $languageId;
        $this->company = $context->company();
    }

    public function getData(): array
    {
        $settings = Settings::getAll();

        if (isset($settings['orderDateCreated'])) {
            if (empty($settings['orderDateCreated'])) {
                $settings['orderDateCreated'] = null;
            } else {
                $settings['orderDateCreated'] = new \DateTime($settings['orderDateCreated']);
            }
        }

        if (!isset($settings['orderStatusToShow'])) {
            $settings['orderStatusToShow'] = $this->getPaidStatusIds();
        }

        if (!isset($settings['productSyncFields'])) {
            $settings['productSyncFields'] = SyncFields::getDefaultFields();
        }

        $settings['companyName'] = $this->getCompanyName();

        return $settings;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function setData(array $data): array
    {
        $shopId = (int) \Shop::getContextShopID();

        $this->settingsRepository->saveSettings($data, $shopId, $this->company->getCompanyId());

        return $data;
    }

    private function getPaidStatusIds(): array
    {
        $ids = [];
        $languageId = (int) \Configuration::get('PS_LANG_DEFAULT');

        $states = \OrderState::getOrderStates($languageId);

        foreach ($states as $state) {
            if ((int) $state['paid'] === 1) {
                $ids[] = (int) $state['id_order_state'];
            }
        }

        return $ids;
    }

    /**
     * Fetch required data for settings form
     *
     * @return $this
     *
     * @throws MoloniApiException
     */
    public function loadMoloniAvailableSettings(): SettingsFormDataProvider
    {
        $measurementUnitsQuery = MoloniApiClient::measurementUnits()->queryMeasurementUnits();
        $warehousesQuery = $this->company->canSyncStock() ? MoloniApiClient::warehouses()->queryWarehouses() : [];
        $documentSetsQuery = MoloniApiClient::documentSets()->queryDocumentSets();
        $countriesQuery = MoloniApiClient::countries()->queryCountries([
            'options' => [
                'defaultLanguageId' => Languages::EN,
            ],
        ]);
        $storesQuery = \Store::getStores($this->languageId);
        $orderStatusQuery = \OrderState::getOrderStates($this->languageId);

        $this->companyName = MoloniContext::instance()->company()->get('name');

        foreach (MoloniContext::instance()->company()->get('fiscalZone')['exemption']['reasons'] ?? [] as $reason) {
            $this->exemptionReasons["{$reason['code']} - {$reason['name']}"] = $reason['code'];
        }

        foreach ($orderStatusQuery as $states) {
            $this->orderStatus[$states['name']] = $states['id_order_state'];
        }

        foreach ($countriesQuery as $country) {
            $this->countries[$country['title']] = $country['countryId'];
        }

        foreach ($measurementUnitsQuery as $measurementUnit) {
            $this->measurementUnits[$measurementUnit['name']] = $measurementUnit['measurementUnitId'];
        }

        foreach ($warehousesQuery as $warehouse) {
            $this->warehouses[$warehouse['name']] = $warehouse['warehouseId'];
        }

        foreach ($documentSetsQuery as $documentSet) {
            $this->documentSets[$documentSet['name']] = $documentSet['documentSetId'];
        }

        foreach ($storesQuery as $store) {
            $this->stores[$store['name']] = $store['id_store'];
        }

        $this->yesNo = [
            $this->trans('No', 'Modules.Molonion.Settings') => Boolean::NO,
            $this->trans('Yes', 'Modules.Molonion.Settings') => Boolean::YES,
        ];

        $this->productInformation = [
            'PrestaShop' => ProductInformation::PRESTASHOP,
            'Moloni ON' => ProductInformation::MOLONI,
        ];

        $this->status = [
            $this->trans('Draft', 'Modules.Molonion.Settings') => DocumentStatus::DRAFT,
            $this->trans('Closed', 'Modules.Molonion.Settings') => DocumentStatus::CLOSED,
        ];

        foreach (DocumentTypes::getDocumentsTypes() as $name => $code) {
            $this->documentTypes[$this->trans($name, 'Modules.Molonion.Settings')] = $code;
        }

        foreach (SyncFields::getSyncFields() as $name => $code) {
            $this->syncFields[$this->trans($name, 'Modules.Molonion.Settings')] = $code;
        }

        $this->documentReference = [
            $this->trans('Order reference', 'Modules.Molonion.Settings') => DocumentReference::REFERENCE,
            $this->trans('Order ID', 'Modules.Molonion.Settings') => DocumentReference::ID,
        ];

        $this->fiscalZoneBasedOn = [
            $this->trans('Billing', 'Modules.Molonion.Settings') => FiscalZone::BILLING,
            $this->trans('Shipping', 'Modules.Molonion.Settings') => FiscalZone::SHIPPING,
            $this->trans('Company', 'Modules.Molonion.Settings') => FiscalZone::COMPANY,
        ];

        $this->addresses = [
            $this->trans('Moloni ON company', 'Modules.Molonion.Settings') => LoadAddress::MOLONI,
            $this->trans('Custom address', 'Modules.Molonion.Settings') => LoadAddress::CUSTOM,
        ];

        $this->customerLanguage = [
            $this->trans('Automatic', 'Modules.Molonion.Settings') => 0,
            $this->trans('Language', 'Modules.Molonion.Settings') => [
                $this->trans('Portuguese', 'Modules.Molonion.Settings') => Languages::PT,
                $this->trans('Spanish', 'Modules.Molonion.Settings') => Languages::ES,
                $this->trans('English', 'Modules.Molonion.Settings') => Languages::EN,
            ],
        ];

        if (!empty($this->stores)) {
            $this->addresses[$this->trans('Stores', 'Modules.Molonion.Settings')] = $this->stores;
        }

        return $this;
    }

    /**
     * Simple translator implementation
     *
     * @param string $string
     * @param string $domain
     *
     * @return string
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function trans(string $string, string $domain): string
    {
        return $this->translator->trans($string, [], $domain);
    }

    /**
     * @return array
     */
    public function getMeasurementUnits(): array
    {
        return $this->measurementUnits;
    }

    /**
     * @return array
     */
    public function getWarehouses(): array
    {
        return $this->warehouses;
    }

    /**
     * @return array
     */
    public function getDocumentSets(): array
    {
        return $this->documentSets;
    }

    /**
     * @return array
     */
    public function getCountries(): array
    {
        return $this->countries;
    }

    /**
     * @return array
     */
    public function getOrderStatus(): array
    {
        return $this->orderStatus;
    }

    /**
     * @return array
     */
    public function getYesNo(): array
    {
        return $this->yesNo;
    }

    /**
     * @return array
     */
    public function getProductInformation(): array
    {
        return $this->productInformation;
    }

    /**
     * @return array
     */
    public function getStatus(): array
    {
        return $this->status;
    }

    /**
     * @return string[]
     */
    public function getDocumentTypes(): array
    {
        return $this->documentTypes;
    }

    /**
     * @return array
     */
    public function getFiscalZoneBasedOn(): array
    {
        return $this->fiscalZoneBasedOn;
    }

    /**
     * @return array
     */
    public function getAddresses(): array
    {
        return $this->addresses;
    }

    /**
     * @return array
     */
    public function getStores(): array
    {
        return $this->stores;
    }

    /**
     * @return string
     */
    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    /**
     * @return array
     */
    public function getSyncFields(): array
    {
        return $this->syncFields;
    }

    /**
     * @return array
     */
    public function getDocumentReference(): array
    {
        return $this->documentReference;
    }

    /**
     * @return array
     */
    public function getCustomerLanguage(): array
    {
        return $this->customerLanguage;
    }

    /**
     * @return array
     */
    public function getExemptionReasons(): array
    {
        return $this->exemptionReasons;
    }
}
