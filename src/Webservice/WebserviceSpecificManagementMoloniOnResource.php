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

use MoloniOn\Webservice\Product\ProductCreate;
use MoloniOn\Webservice\Product\ProductStockChange;
use MoloniOn\Webservice\Product\ProductUpdate;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;

if (!defined('_PS_VERSION_')) {
    exit;
}

class WebserviceSpecificManagementMoloniOnResource implements WebserviceSpecificManagementInterface
{
    /** @var WebserviceOutputBuilder */
    protected $objOutput;

    /** @var string */
    protected $output;

    /** @var WebserviceRequest */
    protected $wsObject;

    /** @var \AppKernel|null Kernel booted to serve the request (kept referenced for the request lifetime) */
    protected $kernel;

    /**
     * Interface method
     *
     * @param WebserviceOutputBuilderCore|WebserviceOutputBuilder $obj
     *
     * @return $this
     */
    public function setObjectOutput($obj): self
    {
        $this->objOutput = $obj;

        return $this;
    }

    /**
     * Interface method
     *
     * @return WebserviceOutputBuilderCore|WebserviceOutputBuilder
     */
    public function getObjectOutput()
    {
        return $this->objOutput;
    }

    /**
     * Interface method
     *
     * @param WebserviceRequestCore|WebserviceRequest $obj
     *
     * @return $this
     */
    public function setWsObject($obj): self
    {
        $this->wsObject = $obj;

        return $this;
    }

    /**
     * Interface method
     */
    public function getWsObject()
    {
        return $this->wsObject;
    }

    /**
     * Ensures the module context is bootstrapped for this request.
     *
     * PrestaShop's /api dispatcher does not boot the Symfony kernel, so the
     * `molonion.context` service (and the static tools it initialises: Settings,
     * SyncLogs, MoloniApi, ProductAssociations) would be missing here. Reuse the
     * running kernel's container when present, otherwise boot one, then get the
     * context service (it wires up those statics on construction).
     *
     * @return void
     */
    protected function bootContext(): void
    {
        $container = SymfonyContainer::getInstance();

        if ($container === null) {
            require_once _PS_ROOT_DIR_ . '/app/AppKernel.php';

            $this->kernel = new \AppKernel(_PS_MODE_DEV_ ? 'dev' : 'prod', (bool) _PS_MODE_DEV_);
            $this->kernel->boot();

            $container = $this->kernel->getContainer();
        }

        $container->get('molonion.context');
    }

    /**
     * Manages the incoming requests
     * Switches between operations
     */
    public function manage()
    {
        $this->bootContext();

        $request = file_get_contents('php://input');
        $request = json_decode($request, true);

        if (!isset($request['model'], $request['operation'], $request['productId']) || $request['model'] !== 'Product') {
            $this->output = 'Bad request';

            return $this->wsObject->getOutputEnabled();
        }

        switch ($request['operation']) {
            case 'create':
                (new ProductCreate((int) $request['productId']))->handle();
                break;
            case 'update':
                (new ProductUpdate((int) $request['productId']))->handle();
                break;
            case 'stockChanged':
                (new ProductStockChange((int) $request['productId']))->handle();
                break;
        }

        $this->output = 'Acknowledge';

        return $this->wsObject->getOutputEnabled();
    }

    /**
     * Interface method
     *
     * @return array|string|null
     */
    public function getContent()
    {
        return $this->objOutput->getObjectRender()->overrideContent($this->output);
    }
}
