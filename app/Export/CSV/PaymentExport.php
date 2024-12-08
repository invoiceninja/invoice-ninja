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

use App\Export\Decorators\Decorator;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Payment;
use App\Transformers\PaymentTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class PaymentExport extends BaseExport
{
    private $entity_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private Decorator $decorator;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new PaymentTransformer();
        $this->decorator = new Decorator();
    }

    private function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->payment_report_keys);
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_client_fields, $this->input['report_keys']));

        $query = Payment::query()
                            ->withTrashed()
                            ->whereHas('client', function ($q) {
                                $q->where('is_deleted', false);
                            })
                            ->where('company_id', $this->company->id)
                            ->where('is_deleted', 0);

        $query = $this->addDateRange($query, 'payments');

        $clients = &$this->input['client_id'];

        if ($clients) {
            $query = $this->addClientFilter($query, $clients);
        }

        $query = $this->addPaymentStatusFilters($query, $this->input['status'] ?? '');

        if ($this->input['document_email_attachment'] ?? false) {
            $this->queueDocuments($query);
        }

        return $query;
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

                    /** @var \App\Models\Payment $resource */
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
                })->toArray();

        return array_merge(['columns' => $header], $report);

    }

    public function run()
    {
        $query =  $this->init();
        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
              ->each(function ($entity) {

                  /** @var \App\Models\Payment $entity */
                  $this->csv->insertOne($this->buildRow($entity));
              });

        return $this->csv->toString();
    }

    private function buildRow(Payment $payment): array
    {
        $transformed_entity = $this->entity_transformer->transform($payment);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'payment' && array_key_exists($parts[1], $transformed_entity)) {
                $entity[$key] = $transformed_entity[$parts[1]];
            } elseif (array_key_exists($key, $transformed_entity)) {
                $entity[$key] = $transformed_entity[$key];
            } else {
                $entity[$key] = $this->decorator->transform($key, $payment);
            }

        }

        $entity = $this->decorateAdvancedFields($payment, $entity);
        return $this->convertFloats($entity);
    }

    private function decorateAdvancedFields(Payment $payment, array $entity): array
    {

        if (in_array('payment.assigned_user_id', $this->input['report_keys'])) {
            $entity['payment.assigned_user_id'] = $payment->assigned_user ? $payment->assigned_user->present()->name() : '';
        }

        if (in_array('payment.user_id', $this->input['report_keys'])) {
            $entity['payment.user_id'] = $payment->user ? $payment->user->present()->name() : '';
        }

        return $entity;
    }
}
