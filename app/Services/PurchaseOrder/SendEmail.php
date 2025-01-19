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

use App\Utils\Ninja;
use App\Models\PurchaseOrder;
use App\Models\VendorContact;
use App\Services\Email\Email;
use App\Jobs\Mail\NinjaMailerJob;
use App\Mail\VendorTemplateEmail;
use App\Services\AbstractService;
use App\Services\Email\EmailObject;
use Illuminate\Support\Facades\App;
use App\Jobs\Mail\NinjaMailerObject;
use App\Events\General\EntityWasEmailed;
use App\Mail\Engine\PurchaseOrderEmailEngine;
use App\Events\PurchaseOrder\PurchaseOrderWasEmailed;

class SendEmail extends AbstractService
{
    public function __construct(protected PurchaseOrder $purchase_order, protected ?string $reminder_template = null, protected ?VendorContact $contact = null)
    {
    }

    /**
     * Builds the correct template to send.
     */
    public function run()
    {
        $this->purchase_order->last_sent_date = now();
        $this->purchase_order->save();
        
        $this->purchase_order->invitations->load('contact.vendor.country', 'purchase_order.vendor.country', 'purchase_order.company')->each(function ($invitation) {

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($invitation->contact->preferredLocale());
            $t->replace(Ninja::transformTranslations($this->purchase_order->company->settings));

            /* Mark entity sent */
            $invitation->purchase_order->service()->markSent()->save();

            $template = 'purchase_order';

            $mo = new EmailObject();
            $mo->entity_id = $invitation->purchase_order_id;
            $mo->template = 'email_template_purchase_order';
            $mo->email_template_body = 'email_template_purchase_order';
            $mo->email_template_subject = 'email_subject_purchase_order';

            $mo->entity_class = get_class($invitation->purchase_order);
            $mo->invitation_id = $invitation->id;
            $mo->client_id = $invitation->vendor->client_id ?? null;
            $mo->vendor_id = $invitation->vendor->vendor_id ?? null;

            Email::dispatch($mo, $invitation->company);
            $this->purchase_order->entityEmailEvent($invitation, $template, $template);

        });

        if ($this->purchase_order->invitations->count() >= 1) {
            event(new EntityWasEmailed($this->purchase_order->invitations->first(), $this->purchase_order->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), 'purchase_order'));
        }

    }
}
