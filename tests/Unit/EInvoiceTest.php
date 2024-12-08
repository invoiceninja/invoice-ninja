<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Company;
use Tests\MockAccountData;
use App\Models\EInvoicingLog;
use App\Jobs\Entity\CreateRawPdf;
use App\Jobs\EDocument\CreateEDocument;
use horstoeko\zugferd\ZugferdDocumentReader;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *   App\Jobs\Invoice\CreateXInvoice
 */
class EInvoiceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
        $this->makeTestData();
    }

    public function testECreditExpiryLogic()
    {
        $a = Account::factory()->create([
            'e_invoice_quota'=>100,
        ]);
        
        $company = Company::factory()->create([
            'account_id' => $a->id,
            'legal_entity_id' => 1,
        ]);

        $log = new EInvoicingLog();
        $log->counter = 100;
        $log->tenant_id = $company->company_key;
        $log->legal_entity_id = 1;
        $log->save();


        for($x=0; $x<50; $x++){

            $log = new EInvoicingLog();
            $log->counter = -1;
            $log->direction = (bool)rand(0, 1) ? 'sent' : 'received';
            $log->tenant_id = $company->company_key;
            $log->legal_entity_id = 1;
            $log->save();

        }

        $this->travelTo(now()->addYears(2));

        $data = $this->getExpiredPurchases([$company->company_key], true);


        $this->assertEquals(100, $data['purchased']);
        $this->assertEquals(-50, $data['sent'] + $data['received']);

        $this->assertEquals(50, $data['purchased'] - abs($data['sent']) - abs($data['received']));
    }

    public function testEInvoiceGenerates()
    {
        $this->company->e_invoice_type = "EN16931";
        $this->invoice->client->routing_id = 'DE123456789';
        $this->invoice->client->save();
        $e_invoice = (new CreateEDocument($this->invoice))->handle();
        $this->assertIsString($e_invoice);
    }

    /**
     * @throws Exception
     */
    public function testValidityofXMLFile()
    {
        $this->company->e_invoice_type = "EN16931";
        $this->invoice->client->routing_id = 'DE123456789';
        $this->invoice->client->save();

        $e_invoice = (new CreateEDocument($this->invoice))->handle();
        $document = ZugferdDocumentReader::readAndGuessFromContent($e_invoice);
        $document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $documentcurrency, $taxcurrency, $taxname, $documentlangeuage, $rest);
        $this->assertEquals($this->invoice->number, $documentno);
    }

    /**
     * @throws Exception
     */
    public function checkEmbededPDFFile()
    {
        $pdf = (new CreateRawPdf($this->invoice->invitations()->first()))->handle();
        $document = ZugferdDocumentReader::readAndGuessFromContent($pdf);
        $document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $documentcurrency, $taxcurrency, $taxname, $documentlangeuage, $rest);
        $this->assertEquals($this->invoice->number, $documentno);
    }




    private function getExpiredPurchases(array $identifier, bool $is_hosted = true): array
    {
        $stub = [
            'purchased' => 0,
            'sent' => 0,
            'received' => 0,
            'period' => now()->subYear()->format('Y-m-d')."|".now()->format('Y-m-d'),
        ];

        $record_query = EInvoicingLog::where('created_at', '<', now()->subYear())
                                        ->where('counter', '>', 0)
                                        ->when($is_hosted, function ($q) use ($identifier){
                                            $q->whereIn('tenant_id', $identifier);
                                        })
                                        ->when(!$is_hosted, function ($q) use ($identifier) {
                                            $q->where('license_key', $identifier[0]);
                                        });

        $log = $record_query->first();
        $stub['purchased'] = $record_query->sum('counter');

        if($stub['purchased'] == 0)
            return $stub;


        $stub['sent'] = EInvoicingLog::where('created_at', '<', now()->subYear())
                                    ->where('counter', '<', 0)
                                    ->where('direction', 'sent')
                                    ->when($is_hosted, function ($q) use ($identifier) {
                                        $q->where('tenant_id', $identifier);
                                    })
                                    ->when(!$is_hosted, function ($q) use ($identifier) {
                                        $q->where('license_key', $identifier);
                                    })
                                    ->sum('counter');


        $stub['received'] = EInvoicingLog::where('created_at', '<', now()->subYear())
                                    ->where('counter', '<', 0)
                                    ->where('direction', 'received')
                                    ->when($is_hosted, function ($q) use ($identifier) {
                                        $q->where('tenant_id', $identifier);
                                    })
                                    ->when(!$is_hosted, function ($q) use ($identifier) {
                                        $q->where('license_key', $identifier);
                                    })
                                    ->sum('counter');

        
        $log->notes = "{$stub['purchased']} purchased, {$stub['sent']} sent, {$stub['received']} received, {$stub['period']} period";
        nlog($log->tenant_id ?? $log->license_key. " : " .$log->notes);
        $log->save();
        
        EInvoicingLog::where('created_at', '<', now()->subYear())
                        ->when($is_hosted, function ($q) use ($identifier) {
                            $q->where('tenant_id', $identifier);
                        })
                        ->when(!$is_hosted, function ($q) use ($identifier) {
                            $q->where('license_key', $identifier);
                        })
                        ->delete();

        
        return $stub;
    }
}
