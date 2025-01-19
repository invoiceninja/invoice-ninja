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

namespace App\Repositories;

use App\Models\Activity;
use App\Models\Backup;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\VendorHtmlEngine;

/**
 * Class for activity repository.
 */
class ActivityRepository extends BaseRepository
{
    use MakesInvoiceHtml;
    use MakesHash;

    /**
     * Save the Activity.
     *
     * @param \stdClass $fields The fields
     * @param \App\Models\Invoice | \App\Models\Quote | \App\Models\Credit | \App\Models\PurchaseOrder | \App\Models\Expense | \App\Models\Payment $entity
     * @param array $event_vars
     */
    public function save($fields, $entity, $event_vars)
    {
        $activity = new Activity();

        foreach ($fields as $key => $value) {
            $activity->{$key} = $value;
        }

        if ($entity->company) {
            $activity->account_id = $entity->company->account_id;
        }

        if ($token_id = $this->getTokenId($event_vars)) {
            $activity->token_id = $token_id;
        }

        $activity->ip = $event_vars['ip'] ?: ' ';
        $activity->is_system = $event_vars['is_system'];

        $activity->save();

        //rate limiter
        if(!in_array($fields->activity_type_id, [Activity::EMAIL_INVOICE, Activity::EMAIL_CREDIT, Activity::EMAIL_QUOTE, Activity::EMAIL_PURCHASE_ORDER])){
            $this->createBackup($entity, $activity);
        }
    }

    /**
     * Creates a backup.
     *
     * @param \App\Models\Invoice | \App\Models\Quote | \App\Models\Credit | \App\Models\PurchaseOrder | \App\Models\Expense $entity
     * @param \App\Models\Activity $activity  The activity
     */
    public function createBackup($entity, $activity)
    {
        if ($entity instanceof User || $entity->company->is_disabled || $entity->company?->account->isFreeHostedClient()) {
            return;
        }

        $entity = $entity->fresh();

        if (get_class($entity) == Invoice::class
            || get_class($entity) == Quote::class
            || get_class($entity) == Credit::class
            || get_class($entity) == RecurringInvoice::class
        ) {
            $backup = new Backup();
            $entity->load('client');
            $backup->amount = $entity->amount;
            $backup->activity_id = $activity->id;
            $backup->json_backup = '';
            $backup->save();

            $backup->storeRemotely($this->generateHtml($entity), $entity->client);

            return;
        }

        if (get_class($entity) == PurchaseOrder::class) {

            $backup = new Backup();
            $entity->load('client');
            $backup->amount = $entity->amount;
            $backup->activity_id = $activity->id;
            $backup->json_backup = '';
            $backup->save();

            $backup->storeRemotely($this->generateVendorHtml($entity), $entity->vendor);

            return;

        }
    }

    public function getTokenId(array $event_vars)
    {
        if ($event_vars['token']) {
            /** @var \App\Models\CompanyToken $company_token **/
            $company_token = CompanyToken::query()->where('token', $event_vars['token'])->first();

            if ($company_token) {
                return $company_token->id;
            }
        }

        return false;
    }

    private function generateVendorHtml($entity)
    {
        $entity_design_id = $entity->design_id ? $entity->design_id : $this->decodePrimaryKey($entity->vendor->getSetting('purchase_order_design_id'));

        $design = Design::withTrashed()->find($entity_design_id);

        if (! $entity->invitations()->exists() || ! $design) {
            return '';
        }

        $entity->load('vendor.company', 'invitations');

        $html = new VendorHtmlEngine($entity->invitations->first()->load('purchase_order', 'contact'));

        if ($design->is_custom) {
            $options = [
                'custom_partials' => json_decode(json_encode($design->design), true),
            ];
            $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
        } else {
            $template = new PdfMakerDesign(strtolower($design->name));
        }

        $state = [
            'template' => $template->elements([
                'vendor' => $entity->vendor,
                'entity' => $entity,
                'pdf_variables' => (array) $entity->company->settings->pdf_variables,
                '$product' => $design->design->product,
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'options' => [
                'all_pages_header' => $entity->vendor->getSetting('all_pages_header'),
                'all_pages_footer' => $entity->vendor->getSetting('all_pages_footer'),
                'vendor' => $entity->vendor,
                'entity' => $entity,
            ],
            'process_markdown' => $entity->vendor->company->markdown_enabled,
        ];

        $maker = new PdfMakerService($state);

        $html = $maker->design($template)
                    ->build()
                    ->getCompiledHTML(true);

        $maker = null;
        $state = null;

        return $html;

    }

    private function generateHtml($entity)
    {
        $entity_design_id = '';
        $entity_type = '';

        if ($entity instanceof Invoice) {
            $entity_type = 'invoice';
            $entity_design_id = 'invoice_design_id';
        } elseif ($entity instanceof RecurringInvoice) {
            $entity_type = 'recurring_invoice';
            $entity_design_id = 'invoice_design_id';
        } elseif ($entity instanceof Quote) {
            $entity_type = 'quote';
            $entity_design_id = 'quote_design_id';
        } elseif ($entity instanceof Credit) {
            $entity_type = 'credit';
            $entity_design_id = 'credit_design_id';
        }

        $entity_design_id = $entity->design_id ? $entity->design_id : $this->decodePrimaryKey($entity->client->getSetting($entity_design_id));

        $design = Design::withTrashed()->find($entity_design_id);

        if (! $entity->invitations()->exists() || ! $design) {
            return '';
        }

        $entity->load('client.company', 'invitations');

        $html = new HtmlEngine($entity->invitations->first()->load($entity_type, 'contact'));

        if ($design->is_custom) {
            $options = [
                'custom_partials' => json_decode(json_encode($design->design), true),
            ];
            $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);
        } else {
            $template = new PdfMakerDesign(strtolower($design->name));
        }

        $state = [
            'template' => $template->elements([
                'client' => $entity->client,
                'entity' => $entity,
                'pdf_variables' => (array) $entity->company->settings->pdf_variables,
                '$product' => $design->design->product,
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'options' => [
                'all_pages_header' => $entity->client->getSetting('all_pages_header'),
                'all_pages_footer' => $entity->client->getSetting('all_pages_footer'),
                'client' => $entity->client,
                'entity' => $entity,
            ],
            'process_markdown' => $entity->client->company->markdown_enabled,
        ];

        $maker = new PdfMakerService($state);

        $html = $maker->design($template)
                     ->build()
                     ->getCompiledHTML(true);

        $maker = null;
        $state = null;

        return $html;
    }
}
