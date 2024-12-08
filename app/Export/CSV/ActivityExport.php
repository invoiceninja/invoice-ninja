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

namespace App\Export\CSV;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\Company;
use App\Models\DateFormat;
use App\Models\Task;
use App\Transformers\ActivityTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class ActivityExport extends BaseExport
{
    public string $date_key = 'created_at';

    private string $date_format = 'YYYY-MM-DD';

    public Writer $csv;

    public array $entity_keys = [
        'date' => 'date',
        'activity' => 'activity',
        'address' => 'address',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;

    }

    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();


        $report = $query->cursor()
            ->map(function ($resource) {
                /** @var \App\Models\Activity $resource */
                $row = $this->buildActivityRow($resource);
                return $this->processMetaData($row, $resource);
            })->toArray();

        return array_merge(['columns' => $header], $report);
    }

    private function buildActivityRow(Activity $activity): array
    {
        return [
        Carbon::parse($activity->created_at)->format($this->date_format),
        ctrans("texts.activity_{$activity->activity_type_id}", [
            'payment_amount' => $activity->payment ? $activity->payment->amount : '',
            'adjustment' => $activity->payment ? $activity->payment->refunded : '',
            'client' => $activity->client ? $activity->client->present()->name() : '',
            'contact' => $activity->contact ? $activity->contact->present()->name() : '',
            'quote' => $activity->quote ? $activity->quote->number : '',
            'user' => $activity->user ? $activity->user->present()->name() : 'System',
            'expense' => $activity->expense ? $activity->expense->number : '',
            'invoice' => $activity->invoice ? $activity->invoice->number : '',
            'recurring_invoice' => $activity->recurring_invoice ? $activity->recurring_invoice->number : '',
            'payment' => $activity->payment ? $activity->payment->number : '',
            'credit' => $activity->credit ? $activity->credit->number : '',
            'task' => $activity->task ? $activity->task->number : '',
            'vendor' => $activity->vendor ? $activity->vendor->present()->name() : '',
            'purchase_order' => $activity->purchase_order ? $activity->purchase_order->number : '',
            'subscription' => $activity->subscription ? $activity->subscription->name : '',
            'vendor_contact' => $activity->vendor_contact ? $activity->vendor_contact->present()->name() : '',
            'recurring_expense' => $activity->recurring_expense ? $activity->recurring_expense->number : '',
        ]),
        $activity->ip,
        ];

    }

    private function init(): Builder
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        /** @var \App\Models\DateFormat $df */
        $df = DateFormat::query()->find($this->company->settings->date_format_id);

        $this->date_format = $df->format;

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->entity_keys);
        }

        $query = Activity::query()
                        ->where('company_id', $this->company->id);

        $query = $this->addDateRange($query, 'activities');

        return $query;
    }

    public function run()
    {
        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        //insert the header
        $this->csv->insertOne($this->buildHeader());


        $query->cursor()
              ->each(function ($entity) {

                  /** @var \App\Models\Activity $entity */

                  $this->buildRow($entity);
              });

        return $this->csv->toString();
    }

    private function buildRow(Activity $activity)
    {

        $this->csv->insertOne($this->buildActivityRow($activity));

    }

    // private function decorateAdvancedFields(Task $task, array $entity): array
    // {
    //     return $entity;
    // }


    public function processMetaData(array $row, $resource): array
    {

        $clean_row = [];

        foreach (array_values($this->input['report_keys']) as $key => $value) {

            $clean_row[$key]['entity'] = 'activity';
            $clean_row[$key]['id'] = $key;
            $clean_row[$key]['hashed_id'] = null;
            $clean_row[$key]['value'] = $row[$key];
            $clean_row[$key]['identifier'] = $value;
            $clean_row[$key]['display_value'] = $row[$key];

        }

        return $clean_row;
    }

}
