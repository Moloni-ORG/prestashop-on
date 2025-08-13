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

namespace MoloniOn\Controller\Admin\Settings;

use MoloniOn\Controller\Admin\MoloniController;
use MoloniOn\Tools\Settings as SettingsTools;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Settings extends MoloniController
{
    public function home(Request $request): Response
    {
        $settingsFormHandler = $this->getSettingsFormHandler();
        $settingsForm = $settingsFormHandler->getForm();
        $settingsForm->handleRequest($request);

        if ($settingsForm->isSubmitted() && $settingsForm->isValid()) {
            try {
                $errors = $settingsFormHandler->save($settingsForm->getData());
            } catch (\Exception $e) {
                $errors = [];
                $errors[] = $e->getMessage();
            }

            if (empty($errors)) {
                $this->addSuccessMessage(
                    $this->trans(
                        'Your module settings were successfuly updated.',
                        'Modules.Molonion.Settings'
                    )
                );

                return $this->redirectToSettings();
            }

            $this->flashErrors($errors);
        }

        return $this->display(
            'settings/Settings.twig',
            [
                'form' => $settingsForm->createView(),
                'companyName' => SettingsTools::get('companyName'),
            ]
        );
    }

    private function getSettingsFormHandler(): object
    {
        return $this->get('molonion.settings.form');
    }
}
