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

namespace MoloniOn;

use Doctrine\ORM\EntityManagerInterface;
use MoloniOn\Api\MoloniApi;
use MoloniOn\Entity\MoloniOnApp;
use MoloniOn\Entity\MoloniOnSettings;
use MoloniOn\Repository\MoloniOnAppRepository;
use MoloniOn\Repository\MoloniOnSettingsRepository;
use MoloniOn\Tools\Logs;
use MoloniOn\Tools\ProductAssociations;
use MoloniOn\Tools\Settings;
use MoloniOn\Tools\SyncLogs;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use PrestaShopBundle\Translation\DataCollectorTranslator;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class MoloniContext
{
    /**
     * EntityManager
     *
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var TranslatorInterface|DataCollectorTranslator
     */
    private $translator;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var MoloniOnApp|null
     */
    private $app;

    /**
     * @var Configurations
     */
    private $configurations;

    /**
     * Current instance of the MoloniContext
     *
     * @var MoloniContext
     */
    private static $instance;

    public function __construct(EntityManagerInterface $entityManager, RouterInterface $router, $translator)
    {
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->translator = $translator;

        $this->init();

        self::$instance = $this;
    }

    //          PRIVATES          //

    private function init(): void
    {
        $this
            ->loadConfigurations()
            ->loadApp()
            ->loadApi()
            ->loadSettings()
            ->loadTools();
    }

    private function loadConfigurations(): MoloniContext
    {
        $this->configurations = new Configurations();

        return $this;
    }

    private function loadApp(): MoloniContext
    {
        /**
         * @var MoloniOnAppRepository $appRepository
         */
        $appRepository = $this
            ->entityManager
            ->getRepository(MoloniOnApp::class);

        $this->app = $appRepository->getApp();

        return $this;
    }

    private function loadApi(): MoloniContext
    {
        new MoloniApi($this->entityManager, $this);

        return $this;
    }

    private function loadSettings(): MoloniContext
    {
        if ($this->app && $companyId = $this->app->getCompanyId()) {
            /**
             * @var MoloniOnSettingsRepository $settingsRepo
             */
            $settingsRepo = $this
                ->entityManager
                ->getRepository(MoloniOnSettings::class);

            $settings = $settingsRepo->getSettings($companyId);
        }

        new Settings($settings ?? []);

        return $this;
    }

    private function loadTools(): void
    {
        new Logs($this->entityManager, $this);
        new SyncLogs($this->entityManager, $this);
        new ProductAssociations($this->entityManager, $this);
    }

    //          Internal instances          //

    public function iTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    public function iRouter(): RouterInterface
    {
        return $this->router;
    }

    public function iEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    //          Module variables          //

    public function getModuleDir(): string
    {
        return _PS_MODULE_DIR_ . 'molonion/';
    }

    public function getViewDir(): string
    {
        return '@Modules/molonion/views/templates/admin/';
    }

    public function getImgPath(): string
    {
        return _MODULE_DIR_ . 'molonion/views/img/';
    }

    public function getCompanyId(): int
    {
        if (empty($this->app)) {
            return 0;
        }

        return $this->app->getCompanyId() ?? 0;
    }

    public function getApp(): ?MoloniOnApp
    {
        return $this->app;
    }

    //          Module specific instances          //

    public function configs(): Configurations
    {
        return $this->configurations;
    }

    //          Statics          //

    public static function instance(): MoloniContext
    {
        return self::$instance;
    }
}
