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

namespace App\Jobs\Ledger;

use App\Libraries\MultiDB;
use App\Models\CompanyLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

//@deprecated
class UpdateLedger implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $deleteWhenMissingModels = true;

    public function __construct(private int $company_ledger_id, private float $start_amount, private string $company_key, private string $db)
    {
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle(): void
    {
        nlog("Updating company ledger for client ". $this->company_ledger_id);

        MultiDB::setDb($this->db);

        $cl = CompanyLedger::find($this->company_ledger_id);

        $ledger_item = $cl->company_ledgerable->company_ledger()->count() == 1;

        nlog($cl->company_ledgerable->company_ledger()->count());

        if (!$cl) {
            return;
        }

        $entity = $cl->company_ledgerable;
        $balance = $entity->calc()->getBalance();
        $cl->adjustment = $ledger_item ? $balance : ($balance - $this->start_amount);

        $parent_ledger = CompanyLedger::query()
            ->where('id', '<', $cl->id)
            ->where('company_id', $cl->company_id)
            ->where('client_id', $cl->client_id)
            ->where('balance', '!=', 0)
            ->orderBy('id', 'DESC')
            ->first();

        $cl->balance = ($parent_ledger ? $parent_ledger->balance : 0) + $cl->adjustment;
        $cl->save();

    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company_key)];
    }
}
