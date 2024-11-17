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

namespace Tests\Integration\Einvoice;

use Tests\TestCase;
use App\Utils\TempFile;
use Tests\MockAccountData;
use InvoiceNinja\EInvoice\EInvoice;
use App\Services\EDocument\Imports\UblEDocument;


/**
 * 
 */
class ImportEInvoiceTest extends TestCase
{
    
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->makeTestData();

        $this->markTestSkipped("testing skipper");
    }

    public function testImportExpenseEinvoice()
    {
        $file = file_get_contents(base_path('tests/Integration/Einvoice/samples/peppol.xml'));
        
        $file = TempFile::UploadedFileFromRaw($file, 'peppol.xml', 'xml');

        $expense = (new UblEDocument($file, $this->company))->run();

        $this->assertNotNull($expense);
        
    }

    public function testParsingDocument()
    {
        $peppol_doc = file_get_contents(base_path('tests/Integration/Einvoice/samples/peppol.xml'));

        //file present
        $this->assertNotNull($peppol_doc);
        
        $e = new EInvoice();
        $invoice = $e->decode('Peppol', $peppol_doc, 'xml');

        //decodes as expected
        $this->assertNotNull($invoice);

        //has prop we expect
        $this->assertObjectHasProperty('UBLVersionID', $invoice);
        
        //has hydrated correctly
        $this->assertInstanceOf(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $invoice);


    }

}