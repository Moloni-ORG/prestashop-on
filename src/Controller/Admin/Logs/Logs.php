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

namespace MoloniOn\Controller\Admin\Logs;

use MoloniOn\Actions\Tools\LogsListDetails;
use MoloniOn\Controller\Admin\MoloniController;
use MoloniOn\Entity\MoloniOnLogs;
use MoloniOn\Enums\LogLevel;
use MoloniOn\Enums\MoloniRoutes;
use MoloniOn\Repository\MoloniOnLogsRepository;
use MoloniOn\Tools\Settings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Logs extends MoloniController
{
    public function home(): Response
    {
        $page = (int) \Tools::getValue('page', 1);

        $filters = \Tools::getValue('filters', []);

        $logs = $paginator = [];

        /** @var MoloniOnLogsRepository $moloniLogsRepository */
        $moloniLogsRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository(MoloniOnLogs::class);

        try {
            ['logs' => $logs, 'paginator' => $paginator] =
                $moloniLogsRepository->getAllPaginated($page, array_merge($filters, ['company_id' => $this->moloniContext->getCompanyId()]));
        } catch (\Exception $e) {
            $msg = $this->trans('Error fetching logs list', 'Modules.Molonion.Errors');

            $this->addErrorMessage($msg);
        }

        $logs = (new LogsListDetails($logs))->handle();

        return $this->display(
            'logs/Logs.twig',
            [
                'logsArray' => $logs,
                'logsLevelsArray' => LogLevel::getLogLevels(),
                'filters' => $filters,
                'paginator' => $paginator,
                'companyName' => Settings::get('companyName'),
                'deleteLogsRoute' => MoloniRoutes::LOGS_DELETE,
                'thisRoute' => MoloniRoutes::LOGS,
            ]
        );
    }

    public function deleteLogs(): RedirectResponse
    {
        /** @var MoloniOnLogsRepository $moloniLogsRepository */
        $moloniLogsRepository = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository(MoloniOnLogs::class);

        $moloniLogsRepository->deleteOlderLogs();

        $msg = $this->trans('Older logs deleted', 'Modules.Molonion.Common');
        $this->addSuccessMessage($msg);

        return $this->redirectToLogs();
    }
}
