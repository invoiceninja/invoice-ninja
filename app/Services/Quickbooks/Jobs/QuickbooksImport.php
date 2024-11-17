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

namespace App\Services\Quickbooks\Jobs;

use App\Models\Client;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Factory\ClientFactory;
use App\Factory\VendorFactory;
use App\Factory\ExpenseFactory;
use App\Factory\InvoiceFactory;
use App\Factory\ProductFactory;
use App\DataMapper\QuickbooksSync;
use App\Factory\ClientContactFactory;
use App\Factory\VendorContactFactory;
use App\DataMapper\QuickbooksSettings;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Quickbooks\QuickbooksService;
use QuickBooksOnline\API\Data\IPPSalesReceipt;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Services\Quickbooks\Transformers\ClientTransformer;
use App\Services\Quickbooks\Transformers\VendorTransformer;
use App\Services\Quickbooks\Transformers\ExpenseTransformer;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;
use App\Services\Quickbooks\Transformers\ProductTransformer;

class QuickbooksImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private array $entities = [
        'product' => 'Item',
        'client' => 'Customer',
        'invoice' => 'Invoice',
        'sales' => 'SalesReceipt',
        // 'quote' => 'Estimate',
        // 'purchase_order' => 'PurchaseOrder',
        // 'payment' => 'Payment',
        // 'vendor' => 'Vendor',
        // 'expense' => 'Purchase',
    ];

    private QuickbooksService $qbs;

    private QuickbooksSync $settings;

    private Company $company;

    public function __construct(public int $company_id, public string $db)
    {
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        MultiDB::setDb($this->db);
     
        $this->company = Company::query()->find($this->company_id);
        $this->qbs = new QuickbooksService($this->company);
        $this->settings =  $this->company->quickbooks->settings;
   
        foreach($this->entities as $key => $entity) {
   
            if(!$this->qbs->syncable($key, \App\Enum\SyncDirection::PULL)) {
                nlog('skipping ' . $key);
                continue;
            }

            $records = $this->qbs->sdk()->fetchRecords($entity);

            $this->processEntitySync($key, $records);

        }

    }
    
    /**
     * Processes the sync for a given entity
     *
     * @param  string $entity
     * @param  mixed $records
     * @return void
     */
    private function processEntitySync(string $entity, $records): void 
    {
        match($entity){
            'client' => $this->qbs->client->syncToNinja($records),
            'product' => $this->qbs->product->syncToNinja($records),
                'invoice' => $this->qbs->invoice->syncToNinja($records),
                'sales' => $this->qbs->invoice->syncToNinja($records),
                // 'vendor' => $this->syncQbToNinjaVendors($records),
                // 'quote' => $this->syncInvoices($records),
                // 'expense' => $this->syncQbToNinjaExpenses($records),
                // 'purchase_order' => $this->syncInvoices($records),
                // 'payment' => $this->syncPayment($records), 
            default => false,
        };
    }

    // private function syncQbToNinjaInvoices($records): void
    // {
       

    // }

    

    // private function syncQbToNinjaVendors(array $records): void
    // {

    //     $transformer = new VendorTransformer($this->company);

    //     foreach($records as $record)
    //     {
    //         $ninja_data = $transformer->qbToNinja($record);

    //         if($vendor = $this->findVendor($ninja_data))
    //         {
    //             $vendor->fill($ninja_data[0]);
    //             $vendor->saveQuietly();

    //             $contact = $vendor->contacts()->where('email', $ninja_data[1]['email'])->first();

    //             if(!$contact)
    //             {
    //                 $contact = VendorContactFactory::create($this->company->id, $this->company->owner()->id);
    //                 $contact->vendor_id = $vendor->id;
    //                 $contact->send_email = true;
    //                 $contact->is_primary = true;
    //                 $contact->fill($ninja_data[1]);
    //                 $contact->saveQuietly(); 
    //             }
    //             elseif($this->qbs->syncable('vendor', \App\Enum\SyncDirection::PULL)){
    //                 $contact->fill($ninja_data[1]);
    //                 $contact->saveQuietly();
    //             }

    //         }

    //     }
    // }

    // private function syncQbToNinjaExpenses(array $records): void
    // {

    //     $transformer = new ExpenseTransformer($this->company);

    //     foreach($records as $record)
    //     {
    //         $ninja_data = $transformer->qbToNinja($record);

    //         if($expense = $this->findExpense($ninja_data))
    //         {
    //             $expense->fill($ninja_data);
    //             $expense->saveQuietly();
    //         }

    //     }
    // }

    // private function findExpense(array $qb_data): ?Expense
    // {
    //     $expense = $qb_data;

    //     $search = Expense::query()
    //                     ->withTrashed()
    //                     ->where('company_id', $this->company->id)
    //                     ->where('number', $expense['number']);
                        
    //     if($search->count() == 0) {
    //         return ExpenseFactory::create($this->company->id, $this->company->owner()->id);
    //     }
    //     elseif($search->count() == 1) {
    //         return $this->qbs->syncable('expense', \App\Enum\SyncDirection::PULL) ? $search->first() : null;
    //     }
        
    //     return null;
    // }

    // private function findVendor(array $qb_data) :?Vendor
    // {
    //     $vendor = $qb_data[0];
    //     $contact = $qb_data[1];
    //     $vendor_meta = $qb_data[2];

    //     $search = Vendor::query()
    //                     ->withTrashed()
    //                     ->where('company_id', $this->company->id)
    //                     ->where(function ($q) use ($vendor, $vendor_meta, $contact){

    //                         $q->where('vendor_hash', $vendor_meta['vendor_hash'])
    //                         ->orWhere('number', $vendor['number'])
    //                         ->orWhereHas('contacts', function ($q) use ($contact){
    //                             $q->where('email', $contact['email']);
    //                         });

    //                     });
                        
    //     if($search->count() == 0) {
    //         //new client
    //         return VendorFactory::create($this->company->id, $this->company->owner()->id);
    //     }
    //     elseif($search->count() == 1) {

    //         return $this->qbs->syncable('vendor', \App\Enum\SyncDirection::PULL) ? $search->first() : null;
    //     }
        
    //     return null;
    // }

    public function middleware()
    {
        return [new WithoutOverlapping("qbs-{$this->company_id}-{$this->db}")];
    }

    public function failed($exception)
    {
        nlog("QuickbooksSync failed => ".$exception->getMessage());
        config(['queue.failed.driver' => null]);

    }
}
