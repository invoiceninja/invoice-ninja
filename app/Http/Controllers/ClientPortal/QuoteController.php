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

use App\Utils\Ninja;
use App\Models\Quote;
use App\Utils\HtmlEngine;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Models\QuoteInvitation;
use App\Utils\Traits\MakesHash;
use App\Events\Quote\QuoteWasViewed;
use App\Http\Controllers\Controller;
use App\Jobs\Invoice\InjectSignature;
use Illuminate\Contracts\View\Factory;
use App\Events\Misc\InvitationWasViewed;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Requests\ClientPortal\Quotes\ShowQuoteRequest;
use App\Http\Requests\ClientPortal\Quotes\ShowQuotesRequest;
use App\Http\Requests\ClientPortal\Quotes\ProcessQuotesInBulkRequest;

class QuoteController extends Controller
{
    use MakesHash;

    /**
     * Display a listing of the quotes.
     *
     * @return Factory|View
     */
    public function index(ShowQuotesRequest $request)
    {
        return $this->render('quotes.index');
    }

    /**
     * Display the specified resource.
     *
     * @param ShowQuoteRequest $request
     * @param Quote $quote
     * @return Factory|View|BinaryFileResponse
     */
    public function show(ShowQuoteRequest $request, Quote $quote)
    {
        /* If the quote is expired, convert the status here */

        $invitation = $quote->invitations()->where('client_contact_id', auth()->guard('contact')->user()->id)->first();
        $variables = ($invitation && auth()->guard('contact')->user()->client->getSetting('show_accept_quote_terms')) ? (new HtmlEngine($invitation))->generateLabelsAndValues() : false;

        $data = [
            'quote' => $quote,
            'key' => $invitation ? $invitation->key : false,
            'invitation' => $invitation,
            'variables' => $variables,
        ];

        if ($invitation && auth()->guard('contact') && ! request()->has('silent') && ! $invitation->viewed_date) {
            $invitation->markViewed();

            event(new InvitationWasViewed($quote, $invitation, $quote->company, Ninja::eventVars()));
            event(new QuoteWasViewed($invitation, $invitation->company, Ninja::eventVars()));
        }

        if ($request->query('mode') === 'fullscreen') {
            return render('quotes.show-fullscreen', $data);
        }

        return $this->render('quotes.show', $data);
    }

    public function bulk(ProcessQuotesInBulkRequest $request)
    {
        $transformed_ids = $this->transformKeys($request->quotes);

        if ($request->action == 'download') {
            return $this->downloadQuotes((array) $transformed_ids);
        }

        if ($request->action == 'approve') {
            return $this->approve((array) $transformed_ids, $request->has('process'));
        }

        return back();
    }

    public function downloadQuotes($ids)
    {
        /** @var \App\Models\ClientContact $client_contact **/
        $client_contact = auth()->user();

        $data['quotes'] = Quote::query()
                            ->whereIn('id', $ids)
                            ->where('client_id', $client_contact->client_id)
                            ->withTrashed()
                            ->get();

        if (count($data['quotes']) == 0) {
            return back()->with(['message' => ctrans('texts.no_items_selected')]);
        }

        return $this->render('quotes.download', $data);
    }

    public function download(Request $request)
    {
        $transformed_ids = $this->transformKeys($request->quotes);

        return $this->downloadQuotePdf((array) $transformed_ids);
    }

    protected function downloadQuotePdf(array $ids)
    {

        /** @var \App\Models\ClientContact $client_contact **/
        $client_contact = auth()->user();

        $quote_invitations = QuoteInvitation::query()
            ->with('quote', 'company')
            ->whereIn('quote_id', $ids)
            ->where('client_contact_id', $client_contact->id)
            ->withTrashed()
            ->get();

        if (! $quote_invitations || $quote_invitations->count() == 0) {
            return redirect()
                ->route('client.quotes.index')
                ->with('message', ctrans('texts.no_quotes_available_for_download'));
        }

        if ($quote_invitations->count() == 1) {
            $invitation = $quote_invitations->first();
            $file = (new \App\Jobs\Entity\CreateRawPdf($invitation))->handle();
            return response()->streamDownload(function () use ($file) {
                echo $file;
            }, $invitation->quote->numberFormatter().".pdf", ['Content-Type' => 'application/pdf']);
        }

        return $this->buildZip($quote_invitations);
    }

    private function buildZip($quote_invitations)
    {
        // create new archive
        $zipFile = new \PhpZip\ZipFile();
        try {
            foreach ($quote_invitations as $invitation) {
                $file = (new \App\Jobs\Entity\CreateRawPdf($invitation))->handle();
                $zipFile->addFromString($invitation->quote->numberFormatter() . '.pdf', $file);
            }

            $filename = date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.quotes')).'.zip';
            $filepath = sys_get_temp_dir().'/'.$filename;

            $zipFile->saveAsFile($filepath) // save the archive to a file
                   ->close(); // close archive

            return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        } catch (\PhpZip\Exception\ZipException $e) {
        } finally {
            $zipFile->close();
        }
    }

    protected function approve(array $ids, $process = false)
    {
        $quotes = Quote::query()
            ->whereIn('id', $ids)
            ->where('client_id', auth()->guard('contact')->user()->client->id)
            ->where('company_id', auth()->guard('contact')->user()->client->company_id)
            ->whereIn('status_id', [Quote::STATUS_DRAFT, Quote::STATUS_SENT])
            ->where(function ($q) {
                $q->whereNull('due_date')->orWhere('due_date', '>=', now());
            })
            ->withTrashed()
            ->get();

        if (! $quotes || $quotes->count() == 0) {
            return redirect()
                ->route('client.quotes.index')
                ->with('message', ctrans('texts.quotes_with_status_sent_can_be_approved'));
        }

        if ($process) {
            foreach ($quotes as $quote) {
                if (request()->has('user_input') && strlen(request()->input('user_input')) > 2) {
                    $quote->po_number = substr(request()->input('user_input'), 0, 180);
                    $quote->saveQuietly();
                }

                $quote->service()->approve(auth()->user())->save();

                if (request()->has('signature') && ! is_null(request()->signature) && ! empty(request()->signature)) {
                    InjectSignature::dispatch($quote, auth()->guard('contact')->user()->id, request()->signature, request()->getClientIp());
                }
            }

            if ($quotes->count() == 1) {
                //forward client to the invoice if it exists
                if ($quotes->first()->invoice()->exists()) {
                    return redirect()->route('client.invoice.show', $quotes->first()->invoice->hashed_id);
                }

                return redirect()->route('client.quote.show', $quotes->first()->hashed_id);
            }

            return redirect()
                ->route('client.quotes.index')
                ->withSuccess('Quote(s) approved successfully.');
        }


        $variables = false;

        if ($invitation = $quotes->first()->invitations()->first() ?? false) {
            $variables = (new HtmlEngine($invitation))->generateLabelsAndValues();
        }

        $variables = ($invitation && auth()->guard('contact')->user()->client->getSetting('show_accept_quote_terms')) ? (new HtmlEngine($invitation))->generateLabelsAndValues() : false;

        return $this->render('quotes.approve', [
            'quotes' => $quotes,
            'variables' => $variables,
        ]);
    }
}
