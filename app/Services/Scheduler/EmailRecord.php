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

use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Str;

class EmailRecord
{
    use MakesHash;

    public function __construct(public Scheduler $scheduler)
    {
    }

    public function run()
    {
        $class = 'App\\Models\\' . Str::camel($this->scheduler->parameters['entity']);

        $entity = $class::find($this->decodePrimaryKey($this->scheduler->parameters['entity_id']));

        if ($entity) {
            $entity->service()->markSent()->sendEmail();
        }

        $this->scheduler->forceDelete();
    }
}
