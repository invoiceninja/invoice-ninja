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

namespace App\Http\Controllers\ClientPortal;

use App\Events\Invoice\InvoiceWasViewed;
use App\Events\Misc\InvitationWasViewed;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Invoices\ProcessInvoicesInBulkRequest;
use App\Http\Requests\ClientPortal\Invoices\ShowInvoiceRequest;
use App\Http\Requests\ClientPortal\Invoices\ShowInvoicesRequest;
use App\Models\CreditInvitation;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    use MakesHash;
    use MakesDates;

    /**
     * Display list of invoices.
     *
     * @return Factory|View
     */
    public function index(ShowInvoicesRequest $request)
    {
        return $this->render('invoices.index');
    }

    /**
     * Show specific invoice.
     *
     * @param ShowInvoiceRequest $request
     * @param Invoice $invoice
     *
     * @return Factory|View
     */
    public function show(ShowInvoiceRequest $request, Invoice $invoice, ?string $hash = null)
    {
        set_time_limit(0);
        
        $invitation = $invoice->invitations()->where('client_contact_id', auth()->guard('contact')->user()->id)->first();

        // @phpstan-ignore-next-line
        if ($invitation && auth()->guard('contact') && ! session()->get('is_silent') && ! $invitation->viewed_date) {
            $invitation->markViewed();

            event(new InvitationWasViewed($invoice, $invitation, $invoice->company, Ninja::eventVars()));
            event(new InvoiceWasViewed($invitation, $invoice->company, Ninja::eventVars()));
        }

        $variables = ($invitation && auth()->guard('contact')->user()->client->getSetting('show_accept_invoice_terms')) ? (new HtmlEngine($invitation))->generateLabelsAndValues() : false;

        $data = [
            'invoice' => $invoice->service()->removeUnpaidGatewayFees()->save(),
            'invitation' => $invitation ?: $invoice->invitations->first(),
            'key' => $invitation ? $invitation->key : false,
            'hash' => $hash,
            'variables' => $variables,
            'invoices' => [$invoice->hashed_id],
            'db' => $invoice->company->db,
        ];

        if ($request->query('mode') === 'fullscreen') {
            return render('invoices.show-fullscreen', $data);
        }

        if(!$invoice->isPayable())
            return $this->render('invoices.show',$data);

        return auth()->guard('contact')->user()->client->getSetting('payment_flow') == 'default' ? $this->render('invoices.show', $data) : $this->render('invoices.show_smooth', $data);

    }

    public function showBlob($hash)
    {
        $data = Cache::get($hash);

        for($x=0; $x<3; $x++){
       
            $data = Cache::get($hash);
            
            if($data)
                break;
    
            usleep(200000);

        }

        $invitation = false;

        match($data['entity_type'] ?? 'invoice') {
            'invoice' => $invitation = InvoiceInvitation::withTrashed()->find($data['invitation_id']),
            'quote' => $invitation = QuoteInvitation::withTrashed()->find($data['invitation_id']),
            'credit' => $invitation = CreditInvitation::withTrashed()->find($data['invitation_id']),
            'recurring_invoice' => $invitation = RecurringInvoiceInvitation::withTrashed()->find($data['invitation_id']),
            default => $invitation = false,
        };

        if (! $invitation) {
            return redirect('/');
        }

        $file = (new \App\Jobs\Entity\CreateRawPdf($invitation))->handle();

        $headers = ['Content-Type' => 'application/pdf'];
        return response()->make($file, 200, $headers);

    }

    /**
     * Pay one or more invoices.
     *
     */
    public function catch_bulk()
    {
        return $this->render('invoices.index');
    }

    public function bulk(ProcessInvoicesInBulkRequest $request)
    {
        $transformed_ids = $this->transformKeys($request->invoices);

        if ($request->input('action') == 'payment') {
            return $this->makePayment((array) $transformed_ids);
        } elseif ($request->input('action') == 'download') {
            return $this->downloadInvoices((array) $transformed_ids);
        }

        return redirect()
            ->back()
            ->with('message', ctrans('texts.no_action_provided'));
    }

    public function downloadInvoices($ids)
    {
        $data['invoices'] = Invoice::query()
                            ->whereIn('id', $ids)
                            ->whereClientId(auth()->guard('contact')->user()->client->id)
                            ->withTrashed()
                            ->get();

        if (count($data['invoices']) == 0) {
            return back()->with(['message' => ctrans('texts.no_items_selected')]);
        }

        return $this->render('invoices.download', $data);
    }

    public function download(Request $request)
    {
        $transformed_ids = $this->transformKeys($request->invoices);

        return $this->downloadInvoicePDF((array) $transformed_ids);
    }

    /**
     * @param array $ids
     * @return Factory|View|RedirectResponse
     */
    private function makePayment(array $ids)
    {
        $invoices = Invoice::query()
                            ->whereIn('id', $ids)
                            ->whereClientId(auth()->guard('contact')->user()->client->id)
                            ->withTrashed()
                            ->get();

        //filter invoices which are payable
        $invoices = $invoices->filter(function ($invoice) {
            return $invoice->isPayable();
        });

        //return early if no invoices.
        if ($invoices->count() == 0) {
            return back()
                ->with('message', ctrans('texts.no_payable_invoices_selected'));
        }

        //ensure all stale fees are removed.
        $invoices->each(function ($invoice) {
            $invoice->service()
                    ->markSent()
                    ->removeUnpaidGatewayFees()
                    ->save();
        });

        $invoices = $invoices->fresh();

        //iterate and sum the payable amounts either partial or balance
        $total = 0;
        foreach ($invoices as $invoice) {
            if ($invoice->partial > 0) {
                $total += $invoice->partial;
            } else {
                $total += $invoice->balance;
            }
        }

        //format data
        $invoices->map(function ($invoice) {
            $invoice->balance = $invoice->balance > 0 ? Number::formatValue($invoice->balance, $invoice->client->currency()) : 0;
            $invoice->partial = $invoice->partial > 0 ? Number::formatValue($invoice->partial, $invoice->client->currency()) : 0;

            return $invoice;
        });

        //format totals
        $formatted_total = Number::formatMoney($total, auth()->guard('contact')->user()->client);

        $payment_methods = auth()->guard('contact')->user()->client->service()->getPaymentMethods($total);

        //if there is only one payment method -> lets return straight to the payment page

        $settings = auth()->guard('contact')->user()->client->getMergedSettings();
        $variables = false;

        if(($invitation = $invoices->first()->invitations()->first() ?? false) && $settings->show_accept_invoice_terms) {
            $variables = (new HtmlEngine($invitation))->generateLabelsAndValues();
        }

        $data = [
            'settings' => $settings,
            'invoices' => $invoices,
            'formatted_total' => $formatted_total,
            'payment_methods' => $payment_methods,
            'hashed_ids' => $invoices->pluck('hashed_id'),
            'total' =>  $total,
            'variables' => $variables,
            'invitation' => $invitation,
            'db' => $invitation->company->db,
        ];

        // return $this->render('invoices.payment', $data);
        return auth()->guard('contact')->user()->client->getSetting('payment_flow') === 'default' ? $this->render('invoices.payment', $data) : $this->render('invoices.show_smooth_multi', $data);
    }

    /**
     * Helper function to download invoice PDFs.
     *
     * @param array $ids
     *
     */
    private function downloadInvoicePDF(array $ids)
    {
        $invoices = Invoice::query()
                            ->whereIn('id', $ids)
                            ->withTrashed()
                            ->whereClientId(auth()->guard('contact')->user()->client->id)
                            ->get();

        //generate pdf's of invoices locally
        if (! $invoices || $invoices->count() == 0) {
            return back()->with(['message' => ctrans('texts.no_items_selected')]);
        }

        //if only 1 pdf, output to buffer for download
        if ($invoices->count() == 1) {
            $invoice = $invoices->first();

            return response()->streamDownload(function () use ($invoice) {
                echo $invoice->service()->getInvoicePdf(auth()->guard('contact')->user());
            }, $invoice->getFileName(), ['Content-Type' => 'application/pdf']);
        }

        return $this->buildZip($invoices);
    }

    private function buildZip($invoices)
    {
        // create new archive
        $zipFile = new \PhpZip\ZipFile();
        try {

            foreach ($invoices as $invoice) {

                if ($invoice->client->getSetting('enable_e_invoice')) {
                    $xml = $invoice->service()->getEInvoice();
                    $zipFile->addFromString($invoice->getFileName("xml"), $xml);
                }

                $file = $invoice->service()->getRawInvoicePdf();
                $zip_file_name = $invoice->getFileName();
                $zipFile->addFromString($zip_file_name, $file);
            }


            $filename = date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.invoices')).'.zip';
            $filepath = sys_get_temp_dir().'/'.$filename;

            $zipFile->saveAsFile($filepath) // save the archive to a file
                   ->close(); // close archive

            return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        } catch (\PhpZip\Exception\ZipException $e) {
            // handle exception
        } finally {
            $zipFile->close();
        }
    }
}
