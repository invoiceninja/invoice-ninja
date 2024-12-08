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
use App\Models\Vendor;
use App\Transformers\VendorContactTransformer;
use App\Transformers\VendorTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class VendorExport extends BaseExport
{
    private $vendor_transformer;

    private $contact_transformer;

    public Writer $csv;

    private Decorator $decorator;

    public string $date_key = 'created_at';

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->vendor_transformer = new VendorTransformer();
        $this->contact_transformer = new VendorContactTransformer();
        $this->decorator = new Decorator();
    }

    public function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->vendor_report_keys);
        }

        $query = Vendor::query()->with('contacts')
                        ->withTrashed()
                        ->where('company_id', $this->company->id);

        if (!$this->input['include_deleted'] ?? false) {
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, 'vendors');

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

                    /** @var \App\Models\Vendor $resource */
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }

    public function run()
    {

        $query = $this->init();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
              ->each(function ($vendor) {

                  /** @var \App\Models\Vendor $vendor */
                  $this->csv->insertOne($this->buildRow($vendor));
              });

        return $this->csv->toString();
    }

    private function buildRow(Vendor $vendor): array
    {
        $transformed_contact = false;

        $transformed_vendor = $this->vendor_transformer->transform($vendor);

        if ($contact = $vendor->contacts()->first()) {
            $transformed_contact = $this->contact_transformer->transform($contact);
        }

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'vendor' && array_key_exists($parts[1], $transformed_vendor)) {
                $entity[$key] = $transformed_vendor[$parts[1]];
            } elseif (is_array($parts) && $parts[0] == 'vendor_contact' && isset($transformed_contact[$parts[1]])) {
                $entity[$key] = $transformed_contact[$parts[1]];
            } else {

                $entity[$key] = $this->decorator->transform($key, $vendor);

            }
        }

        // return $entity;
        return $this->decorateAdvancedFields($vendor, $entity);
    }

    private function decorateAdvancedFields(Vendor $vendor, array $entity): array
    {
        if (in_array('vendor.country_id', $this->input['report_keys'])) {
            $entity['country'] = $vendor->country ? ctrans("texts.country_{$vendor->country->name}") : '';
        }

        if (in_array('vendor.currency', $this->input['report_keys'])) {
            $entity['currency'] = $vendor->currency() ? $vendor->currency()->code : $vendor->company->currency()->code;
        }

        if (in_array('vendor.classification', $this->input['report_keys']) && isset($vendor->classification)) {
            $entity['vendor.classification'] = ctrans("texts.{$vendor->classification}") ?? '';
        }

        if (in_array('vendor.user_id', $this->input['report_keys'])) {
            $entity['vendor.user_id'] = $vendor->user ? $vendor->user->present()->name() : '';
        }

        if (in_array('vendor.assigned_user_id', $this->input['report_keys'])) {
            $entity['vendor.assigned_user_id'] = $vendor->assigned_user ? $vendor->assigned_user->present()->name() : '';
        }


        // $entity['status'] = $this->calculateStatus($vendor);

        return $entity;
    }

    // private function calculateStatus($vendor)
    // {
    //     if ($vendor->is_deleted) {
    //         return ctrans('texts.deleted');
    //     }

    //     if ($vendor->deleted_at) {
    //         return ctrans('texts.archived');
    //     }

    //     return ctrans('texts.active');
    // }
}
