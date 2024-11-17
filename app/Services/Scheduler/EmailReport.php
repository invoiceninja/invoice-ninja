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

namespace App\Services\Scheduler;

use App\Models\Client;
use App\Models\Scheduler;
use App\Mail\DownloadReport;
use App\Export\CSV\TaskExport;
use App\Export\CSV\QuoteExport;
use App\Utils\Traits\MakesHash;
use App\Export\CSV\ClientExport;
use App\Export\CSV\CreditExport;
use App\Utils\Traits\MakesDates;
use App\Export\CSV\ContactExport;
use App\Export\CSV\ExpenseExport;
use App\Export\CSV\InvoiceExport;
use App\Export\CSV\PaymentExport;
use App\Export\CSV\ProductExport;
use App\Jobs\Mail\NinjaMailerJob;
use App\Export\CSV\ActivityExport;
use App\Export\CSV\DocumentExport;
use App\Export\CSV\QuoteItemExport;
use App\Services\Report\ProfitLoss;
use App\Jobs\Mail\NinjaMailerObject;
use App\Export\CSV\InvoiceItemExport;
use App\Export\CSV\ProductSalesExport;
use App\Services\Report\ARDetailReport;
use App\Services\Report\ARSummaryReport;
use App\Services\Report\UserSalesReport;
use App\Services\Report\TaxSummaryReport;
use App\Export\CSV\RecurringInvoiceExport;
use App\Services\Report\ClientSalesReport;
use App\Services\Report\ClientBalanceReport;

class EmailReport
{
    use MakesHash;
    use MakesDates;

    private string $file_name = 'file.csv';

    public function __construct(public Scheduler $scheduler)
    {
    }

    public function run()
    {

        $start_end_dates = $this->calculateStartAndEndDates($this->scheduler->parameters);
        $data = $this->scheduler->parameters;

        $data['start_date'] = $start_end_dates[0];
        $data['end_date'] = $start_end_dates[1];
        $data['date_range'] = $data['date_range'] ?? 'all';
        $data['report_keys'] = $data['report_keys'] ?? [];
        $data['include_deleted'] = $data['include_deleted'] ?? false;

        $export = false;

        match($this->scheduler->parameters['report_name']) {
            'product_sales' => $export = (new ProductSalesExport($this->scheduler->company, $data)),
            'ar_detailed' => $export = (new ARDetailReport($this->scheduler->company, $data)),
            'ar_summary' => $export = (new ARSummaryReport($this->scheduler->company, $data)),
            'tax_summary' => $export = (new TaxSummaryReport($this->scheduler->company, $data)),
            'client_balance' => $export = (new ClientBalanceReport($this->scheduler->company, $data)),
            'client_sales' => $export = (new ClientSalesReport($this->scheduler->company, $data)),
            'user_sales' => $export = (new UserSalesReport($this->scheduler->company, $data)),
            'profitloss' => $export = (new ProfitLoss($this->scheduler->company, $data)),
            'activity' => $export = (new ActivityExport($this->scheduler->company, $data)),
            'activities' => $export = (new ActivityExport($this->scheduler->company, $data)),
            'client' => $export = (new ClientExport($this->scheduler->company, $data)),
            'clients' => $export = (new ClientExport($this->scheduler->company, $data)),
            'client_contact' => $export = (new ContactExport($this->scheduler->company, $data)),
            'client_contacts' => $export = (new ContactExport($this->scheduler->company, $data)),
            'credit' => $export = (new CreditExport($this->scheduler->company, $data)),
            'credits' => $export = (new CreditExport($this->scheduler->company, $data)),
            'document' => $export = (new DocumentExport($this->scheduler->company, $data)),
            'documents' => $export = (new DocumentExport($this->scheduler->company, $data)),
            'expense' => $export = (new ExpenseExport($this->scheduler->company, $data)),
            'expenses' => $export = (new ExpenseExport($this->scheduler->company, $data)),
            'invoice' => $export = (new InvoiceExport($this->scheduler->company, $data)),
            'invoices' => $export = (new InvoiceExport($this->scheduler->company, $data)),
            'invoice_item' => $export = (new InvoiceItemExport($this->scheduler->company, $data)),
            'invoice_items' => $export = (new InvoiceItemExport($this->scheduler->company, $data)),
            'quote' => $export = (new QuoteExport($this->scheduler->company, $data)),
            'quotes' => $export = (new QuoteExport($this->scheduler->company, $data)),
            'quote_item' => $export = (new QuoteItemExport($this->scheduler->company, $data)),
            'quote_items' => $export = (new QuoteItemExport($this->scheduler->company, $data)),
            'recurring_invoice' => $export = (new RecurringInvoiceExport($this->scheduler->company, $data)),
            'recurring_invoices' => $export = (new RecurringInvoiceExport($this->scheduler->company, $data)),
            'payment' => $export = (new PaymentExport($this->scheduler->company, $data)),
            'payments' => $export = (new PaymentExport($this->scheduler->company, $data)),
            'product' => $export = (new ProductExport($this->scheduler->company, $data)),
            'products' => $export = (new ProductExport($this->scheduler->company, $data)),
            'tasks' => $export = (new TaskExport($this->scheduler->company, $data)),
            default => $export = false,
        };

        if(!$export) {
            $this->cancelSchedule();
            return;
        }

        $csv = base64_encode($export->run());
        $files = [];
        $files[] = ['file' => $csv, 'file_name' => "{$this->file_name}", 'mime' => 'text/csv'];

        if (in_array(get_class($export), [ARDetailReport::class, ARSummaryReport::class])) {
            $pdf = base64_encode($export->getPdf());
            $files[] = ['file' => $pdf, 'file_name' => str_replace(".csv", ".pdf", $this->file_name), 'mime' => 'application/pdf'];
        }

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new DownloadReport($this->scheduler->company->withoutRelations(), $files);
        $nmo->company = $this->scheduler->company->withoutRelations();
        $nmo->settings = $this->scheduler->company->settings;
        $nmo->to_user = $this->scheduler->user->withoutRelations();

                
        try {
            (new NinjaMailerJob($nmo))->handle();
        } catch (\Throwable $th) {
            nlog("EXCEPTION:: EmailReport:: could not email report for schdule {$this->scheduler->id} ". $th->getMessage());
        }


        //calculate next run dates;
        $this->scheduler->calculateNextRun();

    }

    private function cancelSchedule()
    {
        $this->scheduler->forceDelete();
    }



}
