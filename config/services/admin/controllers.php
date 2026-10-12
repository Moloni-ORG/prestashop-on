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

/*
 * Symfony 6 (PrestaShop 9) only hands a container to controllers that are service subscribers, so the
 * controllers need autowire + autoconfigure there. PrestaShop 9 does this by itself only for classes
 * that extend FrameworkBundleAdminController directly, not for ours (they extend MoloniController).
 *
 * On Symfony 3.4/4.4 (PrestaShop 1.7/8) this must stay off: the service subscriber container would
 * replace the full container that $this->get() relies on.
 *
 * @var Symfony\Component\DependencyInjection\ContainerBuilder $container
 */
if (Symfony\Component\HttpKernel\Kernel::MAJOR_VERSION < 6) {
    return;
}

foreach ($container->getDefinitions() as $id => $definition) {
    if (strpos((string) $definition->getClass(), 'MoloniOn\\Controller\\') !== 0) {
        continue;
    }

    $definition->setAutowired(true);
    $definition->setAutoconfigured(true);
}
