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

namespace App\Services\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Http\Request;

class TriggeredActions extends AbstractService
{
    use GeneratesCounter;

    private $request;

    private $purchase_order;

    public function __construct(PurchaseOrder $purchase_order, Request $request)
    {
        $this->request = $request;

        $this->purchase_order = $purchase_order;
    }

    public function run()
    {
        if ($this->request->has('send_email') && $this->request->input('send_email') == 'true') {
            $this->purchase_order
                 ->service()
                 ->markSent()
                 ->save();

            $this->sendEmail();
        }

        if ($this->request->has('mark_sent') && $this->request->input('mark_sent') == 'true') {
            $this->purchase_order = $this->purchase_order
                                         ->service()
                                         ->markSent()
                                         ->save();
        }

        if ($this->request->has('save_default_footer') && $this->request->input('save_default_footer') == 'true') {
            $company = $this->purchase_order->company;
            $settings = $company->settings;
            $settings->purchase_order_footer = $this->purchase_order->footer;
            $company->settings = $settings;
            $company->save();
        }

        if ($this->request->has('save_default_terms') && $this->request->input('save_default_terms') == 'true') {
            $company = $this->purchase_order->company;
            $settings = $company->settings;
            $settings->purchase_order_terms = $this->purchase_order->terms;
            $company->settings = $settings;
            $company->save();
        }

        return $this->purchase_order;
    }

    private function sendEmail()
    {
        $this->purchase_order->service()->sendEmail();
    }
}
