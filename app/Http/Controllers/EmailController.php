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

namespace App\Http\Controllers;

use App\DataMapper\InvoiceSync;
use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Webhook;
use App\Models\PurchaseOrder;
use App\Services\Email\Email;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\Services\Email\EmailObject;
use App\Events\Quote\QuoteWasEmailed;
use App\Transformers\QuoteTransformer;
use Illuminate\Mail\Mailables\Address;
use App\Events\Credit\CreditWasEmailed;
use App\Transformers\CreditTransformer;
use App\Transformers\InvoiceTransformer;
use App\Http\Requests\Email\SendEmailRequest;
use App\Jobs\PurchaseOrder\PurchaseOrderEmail;
use App\Transformers\PurchaseOrderTransformer;
use App\Transformers\RecurringInvoiceTransformer;

class EmailController extends BaseController
{
    use MakesHash;

    protected $entity_type = Invoice::class;

    protected $entity_transformer = InvoiceTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    public function send(SendEmailRequest $request)
    {
        $entity = $request->input('entity');
        $entity_obj = $entity::withTrashed()->with('invitations')->find($request->input('entity_id'));
        $subject = $request->has('subject') ? $request->input('subject') : '';
        $body = $request->has('body') ? $request->input('body') : '';
        $template = str_replace('email_template_', '', $request->input('template'));

        $data = [
            'subject' => $subject,
            'body' => $body,
        ];

        $mo = new EmailObject();
        $mo->subject = strlen($subject) > 3 ? $subject : null;
        $mo->body = strlen($body) > 3 ? $body : null;
        $mo->entity_id = $request->input('entity_id');
        $mo->template = $request->input('template'); //full template name in use
        $mo->entity_class = $this->resolveClass($entity);
        $mo->email_template_body = $request->input('template');
        $mo->email_template_subject = str_replace("template", "subject", $request->input('template'));

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company = $entity_obj->company;

        if ($request->cc_email && (Ninja::isSelfHost() || $user->account->isPremium())) {

            foreach ($request->cc_email as $email) {
                $mo->cc[] = new Address($email);
            }

        }

        $entity_obj->invitations->each(function ($invitation) use ($entity_obj, $mo) {
            if (! $invitation->contact->trashed() && $invitation->contact->email) {
                $entity_obj->service()->markSent()->save();

                $mo->invitation_id = $invitation->id;
                $mo->client_id = $invitation->contact->client_id ?? null;
                $mo->vendor_id = $invitation->contact->vendor_id ?? null;

                Email::dispatch($mo, $invitation->company);
            }
        });

        $entity_obj = $entity_obj->fresh();
        $entity_obj->last_sent_date = now();
        $entity_obj->save();

        /*Only notify the admin ONCE, not once per contact/invite*/
        if ($entity_obj instanceof Invoice) {
            $this->entity_type = Invoice::class;
            $this->entity_transformer = InvoiceTransformer::class;

            if ($entity_obj->invitations->count() >= 1) {
                $entity_obj->entityEmailEvent($entity_obj->invitations->first(), $template, $template);
                $entity_obj->sendEvent(Webhook::EVENT_SENT_INVOICE, "client");
            }
        }

        if ($entity_obj instanceof Quote) {
            $this->entity_type = Quote::class;
            $this->entity_transformer = QuoteTransformer::class;

            if ($entity_obj->invitations->count() >= 1) {
                $entity_obj->entityEmailEvent($entity_obj->invitations->first(), $template);
                $entity_obj->sendEvent(Webhook::EVENT_SENT_QUOTE, "client");
            }
        }

        if ($entity_obj instanceof Credit) {
            $this->entity_type = Credit::class;
            $this->entity_transformer = CreditTransformer::class;

            if ($entity_obj->invitations->count() >= 1) {
                event(new CreditWasEmailed($entity_obj->invitations->first(), $entity_obj->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), 'credit'));
                $entity_obj->sendEvent(Webhook::EVENT_SENT_CREDIT, "client");
            }
        }

        if ($entity_obj instanceof RecurringInvoice) {
            $this->entity_type = RecurringInvoice::class;
            $this->entity_transformer = RecurringInvoiceTransformer::class;
        }

        if ($entity_obj instanceof PurchaseOrder) {
            $this->entity_type = PurchaseOrder::class;
            $this->entity_transformer = PurchaseOrderTransformer::class;
        }

        return $this->itemResponse($entity_obj->fresh());
    }

    private function resolveClass(string $entity): string
    {
        $class = '';

        match ($entity) {
            'invoice' => $class = Invoice::class,
            'App\Models\Invoice' => $class = Invoice::class,
            'credit' => $class = Credit::class,
            'App\Models\Credit' => $class = Credit::class,
            'quote' => $class = Quote::class,
            'App\Models\Quote' => $class = Quote::class,
            'purchase_order' => $class = PurchaseOrder::class,
            'purchaseOrder' => $class = PurchaseOrder::class,
            'App\Models\PurchaseOrder' => $class = PurchaseOrder::class,
            default => $class = Invoice::class,
        };

        return $class;
    }
}
