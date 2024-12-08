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

namespace App\Jobs\EDocument;

use App\Utils\Ninja;
use App\Models\Account;
use App\Models\Company;
use App\Utils\TempFile;
use Illuminate\Bus\Queueable;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\EDocument\Gateway\Storecove\Storecove;

class EInvoicePullDocs implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SavesDocuments;

    public $deleteWhenMissingModels = true;

    public $tries = 1;

    public function __construct()
    {
    }

    public function handle() 
    {
        nlog("Pulling Peppol Docs ". now()->format('Y-m-d h:i:s'));
        
        if (Ninja::isHosted()) {
            return;
        }

        Account::query()
                ->with('companies')
                ->where('e_invoice_quota', '>', 0)
                ->whereHas('companies', function ($q) {
                    $q->whereNotNull('legal_entity_id');
                })
                ->cursor()
                ->each(function ($account){

                    $account->companies->filter(function ($company) {

                        return $company->settings->e_invoice_type == 'PEPPOL' && ($company->tax_data->acts_as_receiver ?? false);

                    })
                    ->each(function ($company){

                        $response = \Illuminate\Support\Facades\Http::baseUrl(config('ninja.hosted_ninja_url'))
                            ->withHeaders([
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                                'X-EInvoice-Token' => $company->account->e_invoicing_token,
                            ])
                            ->post('/api/einvoice/peppol/documents', data: [
                                'license_key' => config('ninja.license_key'),
                                'account_key' => $company->account->key,
                                'company_key' => $company->company_key,
                                'legal_entity_id' => $company->legal_entity_id,
                            ]);

                        if($response->successful()){

                            $hash = $response->header('X-CONFIRMATION-HASH');

                            $this->handleSuccess($response->json(), $company, $hash);
                        }
                        else {
                            nlog($response->body());
                        }

                    });
                    
                });
    }

    private function handleSuccess(array $received_documents, Company $company, string $hash): void
    {

        $storecove = new Storecove();

        foreach($received_documents as $document)
        {
            
            $storecove_invoice = $storecove->expense->getStorecoveInvoice(json_encode($document['document']['invoice']));
            $expense = $storecove->expense->createExpense($storecove_invoice, $company);

            $file_name = \Illuminate\Support\Str::ascii(substr($expense->private_notes, 0, 255));

            if(strlen($document['html'] ?? '') > 5)
            {

                $document = TempFile::UploadedFileFromRaw($document['html'], "{$file_name}.html", 'text/html');
                $this->saveDocument($document, $expense);

            }

            if(strlen($document['original_base64_xml'] ?? '') > 5)
            {
                
                $document = TempFile::UploadedFileFromBase64($document['original_base64_xml'], "{$file_name}.xml", 'application/xml');
                $this->saveDocument($document, $expense);

            }
        }

        $response = \Illuminate\Support\Facades\Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-EInvoice-Token' => $company->account->e_invoicing_token,
            ])
            ->post('/api/einvoice/peppol/documents/flush', data: [
                'license_key' => config('ninja.license_key'),
                'account_key' => $company->account->key,
                'company_key' => $company->company_key,
                'legal_entity_id' => $company->legal_entity_id,
                'hash'  => $hash
            ]);

        if($response->successful()){
        }

    }
}