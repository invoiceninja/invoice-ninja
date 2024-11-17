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

namespace App\Services\Report;

use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Client;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Invoice;
use App\Libraries\MultiDB;
use App\Export\CSV\BaseExport;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use App\Services\Template\TemplateService;

class ARSummaryReport extends BaseExport
{
    use MakesDates;

    public Writer $csv;

    public string $date_key = 'created_at';

    public Client $client;

    private float $total = 0;

    private array $clients = [];

    private string $template = '/views/templates/reports/ar_summary_report.html';

    public array $report_keys = [
        'client_name',
        'client_number',
        'id_number',
        'current',
        'age_group_0',
        'age_group_30',
        'age_group_60',
        'age_group_90',
        'age_group_120',
        'total',
    ];

    /**
        @param array $input
        [
            'date_range',
            'start_date',
            'end_date',
            'clients',
            'client_id',
        ]
    */
    public function __construct(public Company $company, public array $input)
    {
    }

    public function run()
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.aged_receivable_summary_report')]);
        $this->csv->insertOne([ctrans('texts.created_on'),' ',$this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale())]);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = $this->report_keys;
        }

        $this->csv->insertOne($this->buildHeader());

        Client::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->orderBy('balance', 'desc')
            ->cursor()
            ->each(function ($client) {

                $this->csv->insertOne($this->buildRow($client));

            });

        return $this->csv->toString();
    }

    public function getPdf()
    {
        $user = isset($this->input['user_id']) ? User::withTrashed()->find($this->input['user_id']) : $this->company->owner();

        $user_name = $user ? $user->present()->name() : '';

        $data = [
            'clients' => $this->clients,
            'company_logo' => $this->company->present()->logo(),
            'company_name' => $this->company->present()->name(),
            'created_on' => $this->translateDate(now()->format('Y-m-d'),$this->company->date_format(), $this->company->locale()),
            'created_by' => $user_name,
        ];

        $ts = new TemplateService();

        $ts_instance = $ts->setCompany($this->company)
                    ->setData($data)
                    ->setRawTemplate(file_get_contents(resource_path($this->template)))
                    ->parseNinjaBlocks()
                    ->save();

        return $ts_instance->getPdf();
    }

    private function buildRow(Client $client): array
    {
        $this->client = $client;

        $row = [
            $this->client->present()->name(),
            $this->client->number,
            $this->client->id_number,
            $this->getCurrent(),
            $this->getAgingAmount('30'),
            $this->getAgingAmount('60'),
            $this->getAgingAmount('90'),
            $this->getAgingAmount('120'),
            $this->getAgingAmount('120+'),
            Number::formatMoney($this->total, $this->company),
        ];

        $this->total = 0;

        $this->clients[] = $row;

        return $row;
    }

    private function getCurrent(): string
    {
        $amount = Invoice::withTrashed()
            ->where('client_id', $this->client->id)
            ->where('company_id', $this->client->company_id)
            ->where('is_deleted', 0)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where(function ($query) {
                $query->where('due_date', '>', now()->startOfDay())
                    ->orWhereNull('due_date');
            })
            ->sum('balance');

        $this->total += $amount;

        return Number::formatMoney($amount, $this->client);

    }
    /**
     * Generate aging amount.
     *
     * @param mixed $range
     * @return string
     */
    private function getAgingAmount($range)
    {
        $ranges = $this->calculateDateRanges($range);

        $from = $ranges[0];
        $to = $ranges[1];

        $amount = Invoice::withTrashed()
            ->where('client_id', $this->client->id)
            ->where('company_id', $this->client->company_id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where('is_deleted', 0)
            ->whereBetween('due_date', [$to, $from])
            ->sum('balance');

        $this->total += $amount;

        return Number::formatMoney($amount, $this->client);
    }

    /**
     * Calculate date ranges for aging.
     *
     * @param mixed $range
     * @return array
     */
    private function calculateDateRanges($range)
    {
        $ranges = [];

        switch ($range) {
            case '30':
                $ranges[0] = now()->startOfDay();
                $ranges[1] = now()->startOfDay()->subDays(30);

                return $ranges;
            case '60':
                $ranges[0] = now()->startOfDay()->subDays(31);
                $ranges[1] = now()->startOfDay()->subDays(60);

                return $ranges;
            case '90':
                $ranges[0] = now()->startOfDay()->subDays(61);
                $ranges[1] = now()->startOfDay()->subDays(90);

                return $ranges;
            case '120':
                $ranges[0] = now()->startOfDay()->subDays(91);
                $ranges[1] = now()->startOfDay()->subDays(120);

                return $ranges;
            case '120+':
                $ranges[0] = now()->startOfDay()->subDays(121);
                $ranges[1] = now()->startOfDay()->subYears(20);

                return $ranges;
            default:
                $ranges[0] = now()->startOfDay()->subDays(0);
                $ranges[1] = now()->subDays(30);

                return $ranges;
        }
    }

    public function buildHeader(): array
    {
        $header = [];

        foreach ($this->input['report_keys'] as $value) {

            $header[] = ctrans("texts.{$value}");
        }

        return $header;
    }

}
