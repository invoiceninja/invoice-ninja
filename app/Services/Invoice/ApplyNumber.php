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

namespace App\Services\Invoice;

use App\Models\Client;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

class ApplyNumber extends AbstractService
{
    use GeneratesCounter;

    private $client;

    private $invoice;

    private $completed = true;

    public function __construct(Client $client, Invoice $invoice)
    {
        $this->client = $client;

        $this->invoice = $invoice;
    }

    public function run()
    {
        if ($this->invoice->number != '') {
            return $this->invoice;
        }

        /** Do not give a pro forma invoice a proper invoice number */
        if ($this->invoice->is_proforma && $this->invoice->recurring_id) {
            $this->invoice->number = ctrans('texts.pre_payment') . " " . now()->format('Y-m-d : H:i:s');
            $this->invoice->saveQuietly();
            return $this->invoice;
        }

        switch ($this->client->getSetting('counter_number_applied')) {
            case 'when_saved':
                $this->trySaving();
                break;
            case 'when_sent':
                if ($this->invoice->status_id >= Invoice::STATUS_SENT) {
                    $this->trySaving();
                }
                break;

            default:
                break;
        }

        return $this->invoice;
    }

    private function trySaving()
    {
        $x = 1;

        do {
            try {
                $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice, $this->invoice->recurring_id);
                $this->invoice->saveQuietly();

                $this->completed = false;
            } catch (QueryException $e) {
                $x++;

                if ($x > 50) {
                    $this->completed = false;
                }
            }
        } while ($this->completed);


        return $this;
    }
}
