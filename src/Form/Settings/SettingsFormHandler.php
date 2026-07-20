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

namespace MoloniOn\Form\Settings;

use MoloniOn\Actions\Tools\WebhookCreate;
use MoloniOn\Actions\Tools\WebhookDeleteAll;
use MoloniOn\Context\Company;
use MoloniOn\Enums\Boolean;
use MoloniOn\Exceptions\MoloniException;
use MoloniOn\MoloniContext;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;
use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class SettingsFormHandler implements FormHandlerInterface
{
    /**
     * @var FormFactoryInterface the form factory
     */
    protected $formFactory;

    /**
     * @var FormDataProviderInterface the form data provider
     */
    protected $formDataProvider;

    /**
     * @var HookDispatcherInterface the event dispatcher
     */
    protected $hookDispatcher;

    /**
     * @var Company
     */
    private $company;

    public function __construct(
        FormFactoryInterface $formFactory,
        HookDispatcherInterface $hookDispatcher,
        SettingsFormDataProvider $formDataProvider,
        MoloniContext $context,
    ) {
        $this->formFactory = $formFactory;
        $this->hookDispatcher = $hookDispatcher;
        $this->formDataProvider = $formDataProvider;
        $this->company = $context->company();
    }

    public function getForm(): FormInterface
    {
        $formBuilder = $this->formFactory->createNamedBuilder(
            'MoloniSettings',
            SettingsFormType::class
        );

        $formBuilder->setData($this->formDataProvider->getData());

        return $formBuilder->getForm();
    }

    public function save(array $data): array
    {
        $this->formDataProvider->setData($data);
        $this->createWebHooks($data);

        return [];
    }

    private function createWebHooks($submitData): void
    {
        if (!$this->company->hasWebhooks()) {
            return;
        }

        try {
            (new WebhookDeleteAll())->handle();
            $action = new WebhookCreate();

            if ($submitData['syncStockToPrestashop'] === Boolean::YES) {
                $action->handle('Product', 'stockChanged');
            }

            if ($submitData['addProductsToPrestashop'] === Boolean::YES) {
                $action->handle('Product', 'create');
            }

            if ($submitData['updateProductsToPrestashop'] === Boolean::YES) {
                $action->handle('Product', 'update');
            }
        } catch (MoloniException $e) {
            // no need to catch anything
        }
    }
}
