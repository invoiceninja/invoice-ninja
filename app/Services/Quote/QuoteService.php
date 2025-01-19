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

use App\Events\Quote\QuoteWasApproved;
use App\Exceptions\QuoteConversion;
use App\Jobs\EDocument\CreateEDocument;
use App\Models\Project;
use App\Models\Quote;
use App\Repositories\QuoteRepository;
use App\Services\Quote\UpdateReminder;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Storage;

class QuoteService
{
    use MakesHash;

    public $quote;

    public $invoice;

    public function __construct($quote)
    {
        $this->quote = $quote;
    }

    public function createInvitations()
    {
        $this->quote = (new CreateInvitations($this->quote))->run();

        return $this;
    }

    public function convertToProject(): Project
    {
        $project = (new ConvertQuoteToProject($this->quote))->run();

        return $project;
    }

    public function convert(): self
    {
        if ($this->quote->invoice_id) {
            throw new QuoteConversion();
        }

        $convert_quote = (new ConvertQuote($this->quote->client))->run($this->quote);

        $this->invoice = $convert_quote;

        $this->quote->fresh();

        if ($this->quote->client->getSetting('auto_archive_quote')) {
            $quote_repo = new QuoteRepository();
            $quote_repo->archive($this->quote);
        }

        return $this;
    }

    public function getQuotePdf($contact = null)
    {
        return (new GetQuotePdf($this->quote, $contact))->run();
    }

    public function getEQuote($contact = null)
    {
        return (new CreateEDocument($this->quote))->handle();
    }

    public function getEDocument($contact = null)
    {
        return $this->getEQuote($contact);
    }

    public function sendEmail($contact = null, $email_type = 'quote'): self
    {
        (new SendEmail($this->quote, $email_type, $contact))->run();

        return $this;
    }

    /**
     * Applies the invoice number.
     * @return $this InvoiceService object
     */
    public function applyNumber(): self
    {
        $apply_number = new ApplyNumber($this->quote->client);

        $this->quote = $apply_number->run($this->quote);

        return $this;
    }

    public function markSent(): self
    {
        $this->quote = (new MarkSent($this->quote->client, $this->quote))->run();

        return $this;
    }

    public function setStatus($status): self
    {
        $this->quote->status_id = $status;

        return $this;
    }

    public function approve($contact = null): self
    {
        $this->setStatus(Quote::STATUS_APPROVED)->save();

        if (! $contact) {
            $contact = $this->quote->invitations->first()->contact;
        }

        if ($this->quote->client->getSetting('auto_convert_quote')) {
            $this->convert();

            $this->invoice
                 ->service()
                 ->markSent()
                 ->save();
        }

        event(new QuoteWasApproved($contact, $this->quote, $this->quote->company, Ninja::eventVars()));

        return $this;
    }



    public function approveWithNoCoversion($contact = null): self
    {
        $this->setStatus(Quote::STATUS_APPROVED)->save();

        if (! $contact) {
            $contact = $this->quote->invitations->first()->contact;
        }

        event(new QuoteWasApproved($contact, $this->quote, $this->quote->company, Ninja::eventVars()));

        return $this;
    }

    public function convertToInvoice()
    {
        $this->convert();

        $this->invoice->service()->createInvitations();

        return $this->invoice;
    }

    public function isConvertable(): bool
    {
        if ($this->quote->invoice_id) {
            return false;
        }

        if ($this->quote->status_id == Quote::STATUS_EXPIRED) {
            return false;
        }

        return true;
    }

    public function fillDefaults()
    {
        $settings = $this->quote->client->getMergedSettings();

        if (! $this->quote->design_id) {
            $this->quote->design_id = $this->decodePrimaryKey($settings->quote_design_id);
        }

        if (! isset($this->quote->footer)) {
            $this->quote->footer = $settings->quote_footer;
        }

        if (! isset($this->quote->terms)) {
            $this->quote->terms = $settings->quote_terms;
        }

        /* If client currency differs from the company default currency, then insert the client exchange rate on the model.*/
        if (! isset($this->quote->exchange_rate) && $this->quote->client->currency()->id != (int) $this->quote->company->settings->currency_id) {
            $this->quote->exchange_rate = $this->quote->client->currency()->exchange_rate;
        }

        if (! isset($this->quote->public_notes)) {
            $this->quote->public_notes = $this->quote->client->public_notes;
        }

        return $this;
    }

    public function triggeredActions($request)
    {
        $this->quote = (new TriggeredActions($this->quote->load('invitations'), $request))->run();

        return $this;
    }

    public function deletePdf()
    {
        $this->quote->invitations->each(function ($invitation) {
            // (new UnlinkFile(config('filesystems.default'), $this->quote->client->quote_filepath($invitation).$this->quote->numberFormatter().'.pdf'))->handle();

            //30-06-2023
            try {
                // if (Storage::disk(config('filesystems.default'))->exists($this->invoice->client->invoice_filepath($invitation).$this->invoice->numberFormatter().'.pdf')) {
                Storage::disk(config('filesystems.default'))->delete($this->quote->client->quote_filepath($invitation).$this->quote->numberFormatter().'.pdf');
                // }

                // if (Ninja::isHosted() && Storage::disk('public')->exists($this->invoice->client->invoice_filepath($invitation).$this->invoice->numberFormatter().'.pdf')) {
                if (Ninja::isHosted()) {
                    Storage::disk('public')->delete($this->quote->client->quote_filepath($invitation).$this->quote->numberFormatter().'.pdf');
                }
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }

        });

        return $this;
    }
    public function deleteEQuote()
    {
        $this->quote->load('invitations');

        $this->quote->invitations->each(function ($invitation) {
            try {
                // if (Storage::disk(config('filesystems.default'))->exists($this->invoice->client->e_invoice_filepath($invitation).$this->invoice->getFileName("xml"))) {
                Storage::disk(config('filesystems.default'))->delete($this->quote->client->e_document_filepath($invitation).$this->quote->getFileName("xml"));
                // }

                // if (Ninja::isHosted() && Storage::disk('public')->exists($this->invoice->client->e_invoice_filepath($invitation).$this->invoice->getFileName("xml"))) {
                if (Ninja::isHosted()) {
                    Storage::disk('public')->delete($this->quote->client->e_document_filepath($invitation).$this->quote->getFileName("xml"));
                }
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }
        });

        return $this;
    }

    public function setReminder($settings = null)
    {
        $this->quote = (new UpdateReminder($this->quote, $settings))->run();

        return $this;
    }


    /*When a reminder is sent we want to touch the dates they were sent*/
    public function touchReminder(string $reminder_template)
    {
        nrlog(now()->format('Y-m-d h:i:s') . " INV #{$this->quote->number} : Touching Reminder => {$reminder_template}");
        switch ($reminder_template) {
            case 'reminder1':
                $this->quote->reminder1_sent = now();
                $this->quote->reminder_last_sent = now();
                $this->quote->last_sent_date = now();
                break;
            case 'reminder2':
                $this->quote->reminder2_sent = now();
                $this->quote->reminder_last_sent = now();
                $this->quote->last_sent_date = now();
                break;
            case 'reminder3':
                $this->quote->reminder3_sent = now();
                $this->quote->reminder_last_sent = now();
                $this->quote->last_sent_date = now();
                break;
            case 'endless_reminder':
                $this->quote->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
            default:
                $this->quote->reminder1_sent = now();
                $this->quote->reminder_last_sent = now();
                $this->quote->last_sent_date = now();
                break;
        }

        return $this;
    }

    /**
     * Saves the quote.
     * @return Quote|null
     */
    public function save(): ?Quote
    {
        $this->quote->saveQuietly();

        return $this->quote;
    }
}
