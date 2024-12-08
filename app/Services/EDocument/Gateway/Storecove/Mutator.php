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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Services\EDocument\Gateway\MutatorUtil;
use App\Services\EDocument\Standards\Peppol\RO;
use App\Services\EDocument\Gateway\MutatorInterface;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;

class Mutator implements MutatorInterface
{
    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice $p_invoice;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    private $invoice;

    private array $storecove_meta = [];

    private MutatorUtil $mutator_util;

    public function __construct(public Storecove $storecove)
    {
        $this->mutator_util = new MutatorUtil($this);
    }

    /**
     * setInvoice
     *
     * @param  mixed $invoice
     * @return self
     */
    public function setInvoice($invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }

    /**
     * setPeppol
     *
     * @param  \InvoiceNinja\EInvoice\Models\Peppol\Invoice $p_invoice
     * @return self
     */
    public function setPeppol($p_invoice): self
    {
        $this->p_invoice = $p_invoice;
        return $this;
    }

    /**
     * getPeppol
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice
     */
    public function getPeppol(): mixed
    {
        return $this->p_invoice;
    }

    /**
     * setClientSettings
     *
     * @param  mixed $client_settings
     * @return self
     */
    public function setClientSettings($client_settings): self
    {
        $this->_client_settings = $client_settings;
        return $this;
    }

    /**
     * setCompanySettings
     *
     * @param  \InvoiceNinja\EInvoice\Models\Peppol\Invoice $company_settings
     * @return self
     */
    public function setCompanySettings($company_settings): self
    {
        $this->_company_settings = $company_settings;
        return $this;
    }

    /**
     * getClientSettings
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice
     */
    public function getClientSettings(): mixed
    {
        return $this->_client_settings;
    }

    /**
     * getCompanySettings
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice
     */
    public function getCompanySettings(): mixed
    {
        return $this->_company_settings;
    }

    /**
     * getInvoice
     *
     * @return mixed
     */
    public function getInvoice(): mixed
    {
        return $this->invoice;
    }

    /**
     * getSetting
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getSetting(string $property_path): mixed
    {
        return $this->mutator_util->getSetting($property_path);
    }
    /**
     * senderSpecificLevelMutators
     *
     * Runs sender level specific requirements for the e-invoice,
     *
     * ie, mutations that are required by the senders country.
     *
     * @return self
     */
    public function senderSpecificLevelMutators(): self
    {

        if (method_exists($this, $this->invoice->company->country()->iso_3166_2)) {
            $this->{$this->invoice->company->country()->iso_3166_2}();
        }

        return $this;
    }

    /**
     * receiverSpecificLevelMutators
     *
     * Runs receiver level specific requirements for the e-invoice
     *
     * ie mutations that are required by the receiving country
     * @return self
     */
    public function receiverSpecificLevelMutators(): self
    {

        if (method_exists($this, "client_{$this->invoice->company->country()->iso_3166_2}")) {
            $this->{"client_{$this->invoice->company->country()->iso_3166_2}"}();
        }

        return $this;
    }

    /**
     * DE
     *
     * @Completed
     * @Tested
     *
     * @return self
     */
    public function DE(): self
    {

        $this->mutator_util->setPaymentMeans(true);

        return $this;
    }

    /**
     * CH
     *
     * @Completed
     *
     * Completed - QR-Bill to be implemented at a later date.
     * @return self
     */
    public function CH(): self
    {
        return $this;
    }

    /**
     * AT
     *
     * @Pending
     *
     * Need to ensure when sending to government entities that we route appropriately
     * Also need to ensure customerAssignedAccountIdValue is set so that the sender can be resolved.
     *
     * Need a way to define if the client is a government entity.
     *
     * @return self
     */
    public function AT(): self
    {
        //special fields for sending to AT:GOV

        if ($this->invoice->client->classification == 'government') {
            //routing "b" for production "test" for test environment
            $this->setStorecoveMeta($this->buildRouting(["scheme" => 'AT:GOV', "id" => 'b']));

            //for government clients this must be set.
            $this->mutator_util->setCustomerAssignedAccountId(true);
        }

        return $this;
    }

    public function AU(): self
    {

        //if payment means are included, they must be the same `type`
        return $this;
    }

    /**
     * ES
     *
     * @Pending
     * B2G configuration
     * B2G Testing
     *
     * testing. // routing identifier - 293098
     *
     * @return self
     */
    public function ES(): self
    {

        if (!isset($this->invoice->due_date)) {
            $this->p_invoice->DueDate = new \DateTime($this->invoice->date);
        }

        if ($this->invoice->client->classification == 'business' && $this->invoice->company->getSetting('classification') == 'business') {
            //must have a paymentmeans as credit_transfer
            $this->mutator_util->setPaymentMeans(true);
        }

        // For B2G, provide three ES:FACE identifiers in the routing object,
        // as well as the ES:VAT tax identifier in the accountingCustomerParty.publicIdentifiers.
        // The invoice will then be routed through the FACe network. The three required ES:FACE identifiers are as follows:
        //   "routing": {
        //     "eIdentifiers":[
        //       {
        //         "scheme": "ES:FACE",
        //         "id": "L01234567",
        //         "role": "ES-01-FISCAL"
        //       },
        //       {
        //         "scheme": "ES:FACE",
        //         "id": "L01234567",
        //         "role": "ES-02-RECEPTOR"
        //       },
        //       {
        //         "scheme": "ES:FACE",
        //         "id": "L01234567",
        //         "role": "ES-03-PAGADOR"
        //       }
        //     ]
        //   }

        return $this;
    }

    /**
     * FI
     *
     * @return self
     */
    public function FI(): self
    {

        // For Finvoice, provide an FI:OPID routing identifier and an FI:OVT legal identifier.
        // An FI:VAT is recommended. In many cases (depending on the sender/receiver country and the type of service/goods)
        // an FI:VAT is required. So we recommend always including this.

        return $this;
    }

    /**
     * FR
     * @Pending - clarification on codes needed
     *
     * @return self
     */
    public function FR(): self
    {

        // When sending invoices to the French government (Chorus Pro):
        // All invoices have to be routed to SIRET 0009:11000201100044. There is no test environment for sending to public entities.
        // The SIRET / 0009 identifier of the final recipient is to be included in the invoice.accountingCustomerParty.publicIdentifiers array.

        if ($this->invoice->client->classification == 'government') {
            //route to SIRET 0009:11000201100044
            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => '11000201100044']

                // ["scheme" => 'FR:SIRET', "id" => '0009:11000201100044']
            ]));

            // The SIRET / 0009 identifier of the final recipient is to be included in the invoice.accountingCustomerParty.publicIdentifiers array.
            $this->mutator_util->setCustomerAssignedAccountId(true);

        }

        if (strlen($this->invoice->client->id_number ?? '') == 9) {
            //SIREN
            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => "{$this->invoice->client->id_number}"]

                // ["scheme" => 'FR:SIRET', "id" => "0002:{$this->invoice->client->id_number}"]
            ]));
        } else {
            //SIRET
            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => "{$this->invoice->client->id_number}"]

                // ["scheme" => 'FR:SIRET', "id" => "0009:{$this->invoice->client->id_number}"]
            ]));
        }

        return $this;
    }

    /**
     * IT
     *
     * @return self
     */
    public function IT(): self
    {

        // IT Sender, IT Receiver, B2B/B2G
        // Provide the receiver IT:VAT and the receiver IT:CUUO (codice destinatario)
        if (in_array($this->invoice->client->classification, ['business','government']) && $this->invoice->company->country()->iso_3166_2 == 'IT') {

            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => 'IT:IVA', "id" => $this->invoice->client->vat_number],
                ["scheme" => 'IT:CUUO', "id" => $this->invoice->client->routing_id]
            ]));

            return $this;
        }

        // IT Sender, IT Receiver, B2C
        // Provide the receiver IT:CF and the receiver IT:CUUO (codice destinatario)
        if ($this->invoice->client->classification == 'individual' && $this->invoice->company->country()->iso_3166_2 == 'IT') {

            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => 'IT:CF', "id" => $this->invoice->client->vat_number],
                // ["scheme" => 'IT:CUUO', "id" => $this->invoice->client->routing_id]
            ]));

            $this->setEmailRouting($this->getIndividualEmailRoute());

            return $this;
        }

        // IT Sender, non-IT Receiver
        // Provide the receiver tax identifier and any routing identifier applicable to the receiving country (see Receiver Identifiers).
        if ($this->invoice->client->country->iso_3166_2 != 'IT' && $this->invoice->company->country()->iso_3166_2 == 'IT') {

            $code = $this->getClientRoutingCode();

            nlog("foreign receiver");
            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => $code, "id" => $this->invoice->client->vat_number]
            ]));

            return $this;
        }

        return $this;
    }

    /**
     * client_IT
     *
     * @return self
     */
    public function client_IT(): self
    {

        // non-IT Sender, IT Receiver, B2C
        // Provide the receiver IT:CF and an optional email. The invoice will be eReported and sent via email. Note that this cannot be a PEC email address.
        if (in_array($this->invoice->client->classification, ['individual']) && $this->invoice->company->country()->iso_3166_2 != 'IT') {

            return $this;
        }

        // non-IT Sender, IT Receiver, B2B/B2G
        // Provide the receiver IT:VAT and the receiver IT:CUUO (codice destinatario)

        return $this;

    }

    /**
     * MY
     *
     * @return self
     */
    public function MY(): self
    {
        //way too much to digest here, delayed.
        return $this;
    }

    /**
     * NL
     *
     * @return self
     */
    public function NL(): self
    {

        // When sending to public entities, the invoice.accountingSupplierParty.party.contact.email is mandatory.

        // Dutch senders and receivers require a legal identifier. For companies, this is NL:KVK, for public entities this is NL:OINO.

        return $this;
    }

    /**
     * NZ
     *
     * @return self
     */
    public function NZ(): self
    {
        // New Zealand uses a GLN to identify businesses. In addition, when sending invoices to a New Zealand customer, make sure you include the pseudo identifier NZ:GST as their tax identifier.
        return $this;
    }

    /**
     * PL
     *
     * @return self
     */
    public function PL(): self
    {

        // Because using this network is not yet mandatory, the default workflow is to not use this network. Therefore, you have to force its use, as follows:

        // "routing": {
        //   "eIdentifiers": [
        //     {
        //         "scheme": "PL:VAT",
        //         "id": "PL0101010101"
        //     }
        //   ],
        //   "networks": [
        //     {
        //       "application": "pl-ksef",
        //       "settings": {
        //         "enabled": true
        //       }
        //     }
        //   ]
        // }
        // Note this will only work if your LegalEntity has been setup for this network.

        return $this;
    }

    /**
     * RO
     *
     * @return self
     */
    public function RO(): self
    {
        // Because using this network is not yet mandatory, the default workflow is to not use this network. Therefore, you have to force its use, as follows:
        $meta = ["networks" => [
                    [
                        "application" => "ro-anaf",
                        "settings" => [
                            "enabled" => true
                        ],
                    ],
                ]];

        $this->setStorecoveMeta($meta);

        $this->setStorecoveMeta($this->buildRouting([
               ["scheme" => 'RO:VAT', "id" => $this->invoice->client->vat_number],
           ]));

        $ro = new RO($this->invoice);

        $client_state = $this->mutator_util->getClientSetting('Invoice.AccountingSupplierParty.Party.PostalAddress.Address.CountrySubentity');
        $client_city = $this->mutator_util->getClientSetting('Invoice.AccountingCustomerParty.Party.PostalAddress.Address.CityName');

        $resolved_state = $ro->getStateCode($client_state);
        $resolved_city = $ro->getSectorCode($client_city);

        $this->p_invoice->AccountingCustomerParty->Party->PostalAddress->CountrySubentity = $resolved_state;
        $this->p_invoice->AccountingCustomerParty->Party->PostalAddress->CityName = $resolved_city;

        return $this;
    }

    /**
     * SG
     *
     * @return self
     */
    public function SG(): self
    {
        //delayed  - stage 2
        return $this;
    }

    //Sweden
    public function SE(): self
    {
        // Deliver invoices to the "Svefaktura" co-operation of local Swedish service providers.
        // Routing is through the SE:ORGNR together with a network specification:

        // "routing": {
        //   "eIdentifiers": [
        //     {
        //         "scheme": "SE:ORGNR",
        //         "id": "0012345678"
        //     }
        //   ],
        //   "networks": [
        //     {
        //       "application": "svefaktura",
        //       "settings": {
        //         "enabled": true
        //       }
        //     }
        //   ]
        // }
        // Use of the "Svefaktura" co-operation can also be induced by specifying an operator id, as follows:

        // "routing": {
        //   "eIdentifiers": [
        //     {
        //         "scheme": "SE:ORGNR",
        //         "id": "0012345678"
        //     },
        //     {
        //         "scheme": "SE:OPID",
        //         "id": "1234567890"
        //     }
        //   ]
        // }
        return $this;
    }

    /////////////// Storecove Helpers ///////////////
    private function getIndividualEmailRoute(): string
    {
        return $this->invoice->client->present()->email();
    }

    private function getClientPublicIdentifier(string $code): string
    {
        if ($this->invoice->client->classification == 'individual' && strlen($this->invoice->client->id_number ?? '') > 2) {
            return $this->invoice->client->id_number;
        }

        return $this->invoice->client->vat_number ?? '';
    }

    public function setClientRoutingCode(): self
    {

        if ($this->invoice->client->classification == 'individual' || (strlen($this->invoice->client->vat_number ?? '') < 2 && strlen($this->invoice->client->id_number ?? '') < 2)) {
            return $this->setEmailRouting($this->getIndividualEmailRoute());
        }

        //Regardless, always include the client email address as a route - Storecove will only use this as a fallback.
        $this->setEmailRouting($this->getIndividualEmailRoute());

        $code = $this->getClientRoutingCode();
        $identifier = false;

        if ($this->invoice->client->country->iso_3166_2 == 'FR') {
            $identifier = $this->invoice->client->id_number;
        } else {
            $identifier = $this->invoice->client->vat_number;
        }

        if ($this->invoice->client->country->iso_3166_2 == 'DE' && $this->invoice->client->classification == 'government') {
            $identifier = $this->invoice->client->routing_id;
        }

        if (!$identifier) {
            $identifier = $this->getClientPublicIdentifier($code);
        }


        $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => $code, "id" => $identifier]
            ]));

        return $this;
    }

    /**
     * getClientRoutingCode
     *
     * @return string
     */
    private function getClientRoutingCode(): string
    {
        return (new StorecoveRouter())->setInvoice($this->invoice)->resolveRouting($this->invoice->client->country->iso_3166_2, $this->invoice->client->classification);
    }


    /**
     * Builds the Routing object for StoreCove
     *
     * @param  array $identifiers
     * @return array
     */
    private function buildRouting(array $identifiers): array
    {
        return
        [
            "routing" => [
                "eIdentifiers" =>
                    $identifiers,

            ]
        ];
    }


    /**
     * setEmailRouting
     *
     * @param  string $email
     * @return self
     */
    private function setEmailRouting(string $email): self
    {
        $meta = $this->getStorecoveMeta();

        if (isset($meta['routing']['emails'])) {
            $emails = $meta['routing']['emails'];
            array_push($emails, $email);
            $meta['routing']['emails'] = $emails;
        } else {
            $meta['routing']['emails'] = [$email];
        }

        $this->setStorecoveMeta($meta);

        return $this;
    }



    /**
     * setStorecoveMeta
     *
     * updates the storecove payload for sending documents
     *
     * @param  array $meta
     * @return self
     */
    private function setStorecoveMeta(array $meta): self
    {

        $this->storecove_meta = array_merge_recursive($this->storecove_meta, $meta);

        return $this;
    }

    /**
     * getStorecoveMeta
     *
     * @return array
     */
    public function getStorecoveMeta(): array
    {
        return $this->storecove_meta;
    }


}
