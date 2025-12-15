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

namespace MoloniOn\Activators;

class ActivatorAbstract
{
    /**
     * The module data
     *
     * @var \CoreModule
     */
    protected $module;

    /**
     * Hooks list
     *
     * @var string[]
     */
    protected $hooks = [
        'actionAdminControllerSetMedia',
        'actionOrderStatusUpdate',
        'actionProductAdd',
        'actionProductUpdate',
        'actionUpdateQuantity',
        'actionGetAdminOrderButtons',
        'addWebserviceResources',
        'actionAdminProductsControllerSaveBefore',
    ];

    /**
     * Plugin tabs list
     *
     * @var array[]
     */
    protected $tabs = [
        [
            'class_name' => 'MoloniOn',
            'parent_class_name' => 'SELL',
            'name' => [
                'en' => 'Moloni ON',
                'es' => 'Moloni ON',
                'pt' => 'Moloni ON',
            ],
            'wording' => 'Moloni ON',
            'wording_domain' => 'Modules.Molonion.Admin',
            'icon' => 'logo',
        ],
        [
            'class_name' => 'MoloniOnOrders',
            'parent_class_name' => 'MoloniOn',
            'name' => [
                'en' => 'Orders',
                'es' => 'Pedidos pendientes',
                'pt' => 'Pedidos pendentes',
            ],
            'wording' => 'Orders',
            'wording_domain' => 'Modules.Molonion.Admin',
            'icon' => '',
        ],
        [
            'class_name' => 'MoloniOnDocuments',
            'parent_class_name' => 'MoloniOn',
            'name' => [
                'en' => 'Documents',
                'es' => 'Documentos creados',
                'pt' => 'Documentos criados',
            ],
            'wording' => 'Documents',
            'wording_domain' => 'Modules.Molonion.Admin',
            'icon' => '',
        ],
        [
            'class_name' => 'MoloniOnSettings',
            'parent_class_name' => 'MoloniOn',
            'name' => [
                'en' => 'Settings',
                'es' => 'Configuraciones',
                'pt' => 'Configurações',
            ],
            'wording' => 'Settings',
            'wording_domain' => 'Modules.Molonion.Admin',
            'icon' => '',
        ],
        [
            'class_name' => 'MoloniOnLogs',
            'parent_class_name' => 'MoloniOn',
            'name' => [
                'en' => 'Logs',
                'es' => 'Registros',
                'pt' => 'Registos',
            ],
            'wording' => 'Logs',
            'wording_domain' => 'Modules.Molonion.Admin',
            'icon' => '',
        ],
        [
            'class_name' => 'MoloniOnTools',
            'parent_class_name' => 'MoloniOn',
            'name' => [
                'en' => 'Tools',
                'es' => 'Herramientas',
                'pt' => 'Ferramentas',
            ],
            'wording' => 'Tools',
            'wording_domain' => 'Modules.Molonion.Admin',
            'icon' => '',
        ],
    ];
}
