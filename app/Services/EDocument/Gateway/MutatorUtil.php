<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Gateway;

use App\Exceptions\PeppolValidationException;
use App\Services\EDocument\Gateway\MutatorInterface;
use App\Services\EDocument\Standards\Settings\PropertyResolver;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CustomerAssignedAccountID;

/**
 * Class MutatorUtil
 *
 * Utility class for e-document mutations.
 */
class MutatorUtil
{
    /**
     * MutatorUtil constructor.
     */
    public function __construct(public MutatorInterface $mutator)
    {
    }

    /**
     * setPaymentMeans
     *
     * Sets the payment means - if it exists
     * 
     * @param  bool $required
     * @return self
     */
    public function setPaymentMeans(bool $required = false): self
    {
        $peppol = $this->mutator->getPeppol();

        if(isset($peppol->PaymentMeans)) {
            return $this;
        } elseif($paymentMeans = $this->getSetting('Invoice.PaymentMeans')) {
            $peppol->PaymentMeans = is_array($paymentMeans) ? $paymentMeans : [$paymentMeans];
            $this->mutator->setPeppol($peppol);
            return $this;
        }

        return $this->checkRequired($required, "Payment Means");

    }


    /**
     * getClientSetting
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getClientSetting(string $property_path): mixed
    {
        return PropertyResolver::resolve($this->mutator->getClientSettings(), $property_path);
    }
    
    /**
     * getCompanySetting
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getCompanySetting(string $property_path): mixed
    {
        return PropertyResolver::resolve($this->mutator->getCompanySettings(), $property_path);
    }

    /**
     * getSetting
     *
     * Attempts to harvest and return a preconfigured prop from company / client / invoice settings
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getSetting(string $property_path): mixed
    {

        if($prop_value = PropertyResolver::resolve($this->mutator->getPeppol(), $property_path)) {
            return $prop_value;
        } elseif($prop_value = PropertyResolver::resolve($this->mutator->getClientSettings(), $property_path)) {
            return $prop_value;
        } elseif($prop_value = PropertyResolver::resolve($this->mutator->getCompanySettings(), $property_path)) {
            return $prop_value;
        }
        return null;

    }

    /**
     * Check Required
     *
     * Throws if a required field is missing.
     *
     * @param  bool $required
     * @param  string $section
     * @return self
     */
    public function checkRequired(bool $required, string $section): self
    {
        return $required ? throw new PeppolValidationException("e-invoice generation halted:: {$section} required", $section, 400) : $this;
    }

    /**
     * setCustomerAssignedAccountId
     *
     * Sets the client id_number CAN rely on settings
     *
     * @param  bool $required
     * @return self
     */
    public function setCustomerAssignedAccountId(bool $required = false): self
    {
        $peppol = $this->mutator->getPeppol();
        $invoice = $this->mutator->getInvoice();

        //@phpstan-ignore-next-line
        if(isset($peppol->AccountingCustomerParty->CustomerAssignedAccountID)) {
            return $this;
        } elseif($customer_assigned_account_id = $this->getSetting('Invoice.AccountingCustomerParty.CustomerAssignedAccountID')) {

            $peppol->AccountingCustomerParty->CustomerAssignedAccountID = $customer_assigned_account_id;
            $this->mutator->setPeppol($peppol);
            return $this;
        } elseif(strlen($invoice->client->id_number ?? '') > 1) {

            $customer_assigned_account_id = new CustomerAssignedAccountID();
            $customer_assigned_account_id->value = $invoice->client->id_number;

            $peppol->AccountingCustomerParty->CustomerAssignedAccountID = $customer_assigned_account_id;
            return $this;
        }

        //@phpstan-ignore-next-line
        return $this->checkRequired($required, 'Client ID Number');

    }

}
