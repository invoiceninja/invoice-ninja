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
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\Task;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 */
class EntityTest extends TestCase
{
    use MockAccountData;
    // use DatabaseTransactions;

    public $invoice;

    public $invoice_calc;

    public $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->invoice->line_items = $this->buildLineItems();

        $this->invoice_calc = new InvoiceSum($this->invoice);
    }

    public function testEntityNameResolution()
    {
        $entity_type = $this->invoice->getEntityType();

        $this->assertEquals('Invoice', class_basename($entity_type));

        $invitation = $this->invoice->invitations->first();

        $entity_type = $invitation->getEntityType();

        $this->assertEquals('InvoiceInvitation', class_basename($entity_type));

        $this->assertEquals('InvoiceInvitation', class_basename($invitation));

        $this->invoice->forceDelete();
    }

    public function testDocumentRelationExists()
    {

        $this->assertTrue(method_exists(Invoice::class, 'documents'));
        $this->assertTrue(method_exists(Quote::class, 'documents'));
        $this->assertTrue(method_exists(Credit::class, 'documents'));
        $this->assertTrue(method_exists(PurchaseOrder::class, 'documents'));
        $this->assertTrue(method_exists(Client::class, 'documents'));
        $this->assertTrue(method_exists(Vendor::class, 'documents'));
        $this->assertTrue(method_exists(Product::class, 'documents'));
        $this->assertTrue(method_exists(Payment::class, 'documents'));
        $this->assertTrue(method_exists(Expense::class, 'documents'));
        $this->assertTrue(method_exists(Project::class, 'documents'));
        $this->assertTrue(method_exists(Task::class, 'documents'));

    }

    protected function tearDown(): void
    {
        
            
        // $this->company->company_users->each(function ($company_user) {
        //     $company_user->user->forceDelete();
        //     $company_user->forceDelete();
        // });

        // // Clean up any resources or reset state if necessary
        // $this->account->delete();


        parent::tearDown();
    }
}
