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

namespace MoloniOn\Api\Endpoints\Documents;

use MoloniOn\Api\Endpoints\Endpoint;
use MoloniOn\Exceptions\MoloniApiException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class InvoiceReceipt extends Endpoint
{
    /**
     * Gets invoice-receipt information
     *
     * @param array|null $variables array variables of the request
     *
     * @return array Api data
     *
     * @throws MoloniApiException
     */
    public function queryInvoiceReceipt(?array $variables = []): array
    {
        $query = $this->loadQuery('invoiceReceipt');

        return $this->simplePost($query, $variables);
    }

    /**
     * Gets all invoice-receipts
     *
     * @param array|null $variables array variables of the request
     *
     * @return array Api data
     *
     * @throws MoloniApiException
     */
    public function queryInvoiceReceipts(?array $variables = []): array
    {
        $query = $this->loadQuery('invoiceReceipts');

        return $this->paginatedPost($query, $variables, 'invoiceReceipts');
    }

    /**
     * Get document token and path for invoice-receipt
     *
     * @param array|null $variables
     *
     * @return array returns the Graphql response array or an error array
     *
     * @throws MoloniApiException
     */
    public function queryInvoiceReceiptGetPDFToken(?array $variables = []): array
    {
        $query = $this->loadQuery('invoiceReceiptGetPDFToken');

        return $this->simplePost($query, $variables);
    }

    /**
     * Creates an invoice-receipt
     *
     * @param array|null $variables variables of the request
     *
     * @return array Api data
     *
     * @throws MoloniApiException
     */
    public function mutationInvoiceReceiptCreate(?array $variables = []): array
    {
        $query = $this->loadMutation('invoiceReceiptCreate');

        return $this->simplePost($query, $variables);
    }

    /**
     * Update an invoice-receipt
     *
     * @param array|null $variables variables of the request
     *
     * @return array Api data
     *
     * @throws MoloniApiException
     */
    public function mutationInvoiceReceiptUpdate(?array $variables = []): array
    {
        $query = $this->loadMutation('invoiceReceiptUpdate');

        return $this->simplePost($query, $variables);
    }

    /**
     * Creates invoice-receipt pdf
     *
     * @param array|null $variables
     *
     * @return array returns the Graphql response array or an error array
     *
     * @throws MoloniApiException
     */
    public function mutationInvoiceReceiptGetPDF(?array $variables = []): array
    {
        $query = $this->loadMutation('invoiceReceiptGetPDF');

        return $this->simplePost($query, $variables);
    }

    /**
     * Send invoice-receipt by mail
     *
     * @param array|null $variables
     *
     * @return array
     *
     * @throws MoloniApiException
     */
    public function mutationInvoiceReceiptSendEmail(?array $variables = []): array
    {
        $query = $this->loadMutation('invoiceReceiptSendMail');

        return $this->simplePost($query, $variables);
    }
}
