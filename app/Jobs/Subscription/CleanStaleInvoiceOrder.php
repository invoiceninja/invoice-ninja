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

namespace App\Jobs\Subscription;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class CleanStaleInvoiceOrder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    /**
     * Create a new job instance.
     *
     */
    public function __construct()
    {
    }

    /**
     * @param InvoiceRepository $repo
     * @return void
     */
    public function handle(InvoiceRepository $repo): void
    {
        nlog("Cleaning Stale Invoices:");

        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {
            Invoice::query()
                    ->withTrashed()
                    ->where('status_id', Invoice::STATUS_SENT)
                    ->where('is_proforma', 1)
                    ->where('created_at', '<', now()->subHour())
                    ->cursor()
                    ->each(function ($invoice) use ($repo) {
                        $invoice->is_proforma = false;
                        $repo->delete($invoice);
                    });

            Invoice::query()
                   ->withTrashed()
                   ->where('status_id', Invoice::STATUS_SENT)
                   ->where('updated_at', '<', now()->subHour())
                   ->where('balance', '>', 0)
                   ->whereJsonContains('line_items', ['type_id' => '3'])
                   ->cursor()
                   ->each(function ($invoice) {
                       $invoice->service()->removeUnpaidGatewayFees();
                   });


            Invoice::query()
                   ->withTrashed()
                   ->where('status_id', Invoice::STATUS_PARTIAL)
                   ->where('balance', '>', 0)
                   ->whereJsonContains('line_items', ['type_id' => '3'])
                   ->cursor()
                   ->each(function ($invoice) {

                       $type_3_count = 0;
                       $type_4_count = 0;

                       foreach ($invoice->line_items as $line_item) {
                           if ($line_item->type_id == '3') {
                               $type_3_count++;
                           } elseif ($line_item->type_id == '4') {
                               $type_4_count++;
                           }
                       }

                       if ($type_4_count == 1) {
                           $invoice->service()->removeUnpaidGatewayFees();
                       } elseif ($type_3_count == 1) {

                           $items = $invoice->line_items;

                           foreach ($items as $key => $value) {

                               if ($value->type_id == "3") {
                                   $items[$key]->type_id = "4";
                               }

                           }

                           $invoice->line_items = array_values($items);
                           $invoice->calc()->getInvoice();

                       }

                   });


            Invoice::query()
                    ->withTrashed()
                    ->where('status_id', Invoice::STATUS_PAID)
                    ->whereJsonContains('line_items', ['type_id' => '3'])
                    ->cursor()
                    ->each(function ($invoice) {

                        $type_3_count = 0;
                        $type_4_count = 0;

                        foreach ($invoice->line_items as $line_item) {
                            if ($line_item->type_id == '3') {
                                $type_3_count++;
                            } elseif ($line_item->type_id == '4') {
                                $type_4_count++;
                            }
                        }

                        if ($type_4_count == 0 && $type_3_count == 1) {

                            $items = $invoice->line_items;

                            foreach ($items as $key => $value) {

                                if ($value->type_id == "3") {
                                    $items[$key]->type_id = "4";
                                }

                            }

                            $invoice->line_items = array_values($items);
                            $invoice->saveQuietly();

                        }

                    });

            return;
        }


        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            Invoice::query()
                ->withTrashed()
                ->where('status_id', Invoice::STATUS_SENT)
                ->where('is_proforma', 1)
                ->where('created_at', '<', now()->subHour())
                ->cursor()
                ->each(function ($invoice) use ($repo) {
                    $invoice->is_proforma = false;
                    $repo->delete($invoice);
                });

            Invoice::query()
                ->withTrashed()
                ->where('status_id', Invoice::STATUS_SENT)
                ->where('updated_at', '<', now()->subHour())
                ->where('balance', '>', 0)
                ->whereJsonContains('line_items', ['type_id' => '3'])
                ->cursor()
                ->each(function ($invoice) {
                    $invoice->service()->removeUnpaidGatewayFees();
                });

            Invoice::query()
                ->withTrashed()
                ->where('status_id', Invoice::STATUS_PARTIAL)
                ->whereJsonContains('line_items', ['type_id' => '3'])
                ->cursor()
                ->each(function ($invoice) {

                    $type_3_count = 0;
                    $type_4_count = 0;

                    foreach ($invoice->line_items as $line_item) {
                        if ($line_item->type_id == '3') {
                            $type_3_count++;
                        } elseif ($line_item->type_id == '4') {
                            $type_4_count++;
                        }
                    }

                    if ($type_4_count == 1) {
                        $invoice->service()->removeUnpaidGatewayFees();
                    } elseif ($type_3_count == 1) {

                        $items = $invoice->line_items;

                        foreach ($items as $key => $value) {

                            if ($value->type_id == "3") {
                                $items[$key]->type_id = "4";
                            }

                        }

                        $invoice->line_items = array_values($items);
                        $invoice->calc()->getInvoice();

                    }

                });


            Invoice::query()
                    ->withTrashed()
                    ->where('status_id', Invoice::STATUS_PAID)
                    ->whereJsonContains('line_items', ['type_id' => '3'])
                    ->cursor()
                    ->each(function ($invoice) {

                        $type_3_count = 0;
                        $type_4_count = 0;


                        foreach ($invoice->line_items as $line_item) {
                            if ($line_item->type_id == '3') {
                                $type_3_count++;
                            } elseif ($line_item->type_id == '4') {
                                $type_4_count++;
                            }
                        }

                        if ($type_4_count == 0 && $type_3_count == 1) {

                            $items = $invoice->line_items;

                            foreach ($items as $key => $value) {

                                if ($value->type_id == "3") {
                                    $items[$key]->type_id = "4";
                                }

                            }

                            $invoice->line_items = array_values($items);
                            $invoice->saveQuietly();

                        }

                    });


            \DB::connection($db)->table('password_resets')->where('created_at', '<', now()->subHours(12))->delete();

        }
    }

    public function failed($exception = null)
    {
    }
}
