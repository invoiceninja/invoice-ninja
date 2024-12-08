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

namespace App\Services\Quote;

use App\Models\Quote;
use App\Models\Invoice;
use App\Jobs\Util\UploadFile;
use App\Utils\Traits\MakesHash;
use App\Repositories\InvoiceRepository;
use App\Factory\InvoiceInvitationFactory;
use App\Factory\CloneQuoteToInvoiceFactory;
use App\Jobs\Document\CopyDocs;
use App\Utils\Traits\GeneratesConvertedQuoteCounter;

class ConvertQuote
{
    use MakesHash;
    use GeneratesConvertedQuoteCounter;

    private $client;

    private $invoice_repo;

    public function __construct($client)
    {
        $this->client = $client;
        $this->invoice_repo = new InvoiceRepository();
    }

    /**
     * @param $quote
     * @return mixed
     */
    public function run($quote)
    {
        $invoice = CloneQuoteToInvoiceFactory::create($quote, $quote->user_id);
        $invoice->design_id = $this->decodePrimaryKey($this->client->getSetting('invoice_design_id'));

        //create invitations here before the repo save()
        //we need to do this here otherwise the repo_save will create
        //invitations for ALL contacts
        $invites = $this->createConversionInvitations($invoice, $quote);
        $invoice_array = $invoice->toArray();
        $invoice_array['invitations'] = $invites;

        //try and convert the invoice number to a quote number here.
        if ($this->client->getSetting('shared_invoice_quote_counter')) {
            $converted_number = $this->harvestQuoteCounter($quote, $invoice, $this->client);

            if ($converted_number) {
                $invoice_array['number'] = $converted_number;
            }
        }

        $invoice = $this->invoice_repo->save($invoice_array, $invoice);

        $invoice->fresh();

        $invoice->service()
                ->fillDefaults()
                ->adjustInventory()
                ->save();

        $quote->invoice_id = $invoice->id;
        $quote->status_id = Quote::STATUS_CONVERTED;
        $quote->save();

        if ($quote->documents()->count() > 0) {
            CopyDocs::dispatch($quote->documents()->pluck('id'), $invoice, $invoice->company->db);
        }

        return $invoice;
    }

    /**
     * Only create the invitations that are defined on the quote.
     *
     */
    private function createConversionInvitations($invoice, $quote)
    {
        $invites = [];

        foreach ($quote->invitations as $quote_invitation) {
            $ii = InvoiceInvitationFactory::create($invoice->company_id, $invoice->user_id);
            $ii->key = $this->createDbHash($invoice->company->db);
            $ii->client_contact_id = $quote_invitation->client_contact_id;

            $invites[] = $ii;
        }

        return $invites;
    }
}
