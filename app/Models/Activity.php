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

namespace App\Models;

use App\Utils\Number;
use App\Utils\Traits\MakesHash;

/**
 * App\Models\Activity
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $company_id
 * @property int|null $client_id
 * @property int|null $client_contact_id
 * @property int|null $account_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property int|null $payment_id
 * @property int|null $invoice_id
 * @property int|null $credit_id
 * @property int|null $invitation_id
 * @property int|null $task_id
 * @property int|null $expense_id
 * @property int|null $activity_type_id
 * @property string $ip
 * @property bool $is_system
 * @property string $notes
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $token_id
 * @property int|null $quote_id
 * @property int|null $subscription_id
 * @property int|null $recurring_invoice_id
 * @property int|null $recurring_expense_id
 * @property int|null $recurring_quote_id
 * @property int|null $purchase_order_id
 * @property int|null $vendor_contact_id
 * @property-read \App\Models\Backup|null $backup
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\ClientContact|null $contact
 * @property-read \App\Models\Credit|null $credit
 * @property-read \App\Models\Expense|null $expense
 * @property-read mixed $hashed_id
 * @property-read \App\Models\Backup|null $history
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\Payment|null $payment
 * @property-read \App\Models\PurchaseOrder|null $purchase_order
 * @property-read \App\Models\Quote|null $quote
 * @property-read \App\Models\RecurringExpense|null $recurring_expense
 * @property-read \App\Models\RecurringInvoice|null $recurring_invoice
 * @property-read \App\Models\Subscription|null $subscription
 * @property-read \App\Models\Task|null $task
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Vendor|null $vendor
 * @property-read \App\Models\VendorContact|null $vendor_contact
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)

 * @mixin \Eloquent
 */
class Activity extends StaticModel
{
    use MakesHash;

    public const CREATE_CLIENT = 1; //

    public const ARCHIVE_CLIENT = 2; //

    public const DELETE_CLIENT = 3; //

    public const CREATE_INVOICE = 4; //

    public const UPDATE_INVOICE = 5; //

    public const EMAIL_INVOICE = 6; //

    public const VIEW_INVOICE = 7; //

    public const ARCHIVE_INVOICE = 8; //

    public const DELETE_INVOICE = 9; //

    public const CREATE_PAYMENT = 10; //

    public const UPDATE_PAYMENT = 11; //

    public const ARCHIVE_PAYMENT = 12; //

    public const DELETE_PAYMENT = 13; //

    public const CREATE_CREDIT = 14; //

    public const UPDATE_CREDIT = 15; //

    public const ARCHIVE_CREDIT = 16; //

    public const DELETE_CREDIT = 17; //

    public const CREATE_QUOTE = 18; //

    public const UPDATE_QUOTE = 19; //

    public const EMAIL_QUOTE = 20; //

    public const VIEW_QUOTE = 21; //

    public const ARCHIVE_QUOTE = 22; //

    public const DELETE_QUOTE = 23; //

    public const RESTORE_QUOTE = 24; //

    public const RESTORE_INVOICE = 25; //

    public const RESTORE_CLIENT = 26; //

    public const RESTORE_PAYMENT = 27; //

    public const RESTORE_CREDIT = 28; //

    public const APPROVE_QUOTE = 29; //

    public const CREATE_VENDOR = 30; //

    public const ARCHIVE_VENDOR = 31; //

    public const DELETE_VENDOR = 32; //

    public const RESTORE_VENDOR = 33; //

    public const CREATE_EXPENSE = 34; //

    public const ARCHIVE_EXPENSE = 35; //

    public const DELETE_EXPENSE = 36; //

    public const RESTORE_EXPENSE = 37; //

    public const VOIDED_PAYMENT = 39; //

    public const REFUNDED_PAYMENT = 40; //

    public const FAILED_PAYMENT = 41;

    public const CREATE_TASK = 42; //

    public const UPDATE_TASK = 43; //

    public const ARCHIVE_TASK = 44; //

    public const DELETE_TASK = 45; //

    public const RESTORE_TASK = 46; //

    public const UPDATE_EXPENSE = 47; //

    public const CREATE_USER = 48;

    public const UPDATE_USER = 49;

    public const ARCHIVE_USER = 50;

    public const DELETE_USER = 51;

    public const RESTORE_USER = 52;

    public const MARK_SENT_INVOICE = 53; // not needed?

    public const PAID_INVOICE = 54; //

    public const EMAIL_INVOICE_FAILED = 57;

    public const REVERSED_INVOICE = 58; //

    public const CANCELLED_INVOICE = 59; //

    public const VIEW_CREDIT = 60; //

    public const UPDATE_CLIENT = 61; //

    public const UPDATE_VENDOR = 62; //

    public const INVOICE_REMINDER1_SENT = 63;

    public const INVOICE_REMINDER2_SENT = 64;

    public const INVOICE_REMINDER3_SENT = 65;

    public const INVOICE_REMINDER_ENDLESS_SENT = 66;

    public const CREATE_SUBSCRIPTION = 80;

    public const UPDATE_SUBSCRIPTION = 81;

    public const ARCHIVE_SUBSCRIPTION = 82;

    public const DELETE_SUBSCRIPTION = 83;

    public const RESTORE_SUBSCRIPTION = 84;

    public const CREATE_RECURRING_INVOICE = 100;

    public const UPDATE_RECURRING_INVOICE = 101;

    public const ARCHIVE_RECURRING_INVOICE = 102;

    public const DELETE_RECURRING_INVOICE = 103;

    public const RESTORE_RECURRING_INVOICE = 104;

    public const CREATE_RECURRING_QUOTE = 110;

    public const UPDATE_RECURRING_QUOTE = 111;

    public const ARCHIVE_RECURRING_QUOTE = 112;

    public const DELETE_RECURRING_QUOTE = 113;

    public const RESTORE_RECURRING_QUOTE = 114;

    public const CREATE_RECURRING_EXPENSE = 120;

    public const UPDATE_RECURRING_EXPENSE = 121;

    public const ARCHIVE_RECURRING_EXPENSE = 122;

    public const DELETE_RECURRING_EXPENSE = 123;

    public const RESTORE_RECURRING_EXPENSE = 124;

    public const CREATE_PURCHASE_ORDER = 130;

    public const UPDATE_PURCHASE_ORDER = 131;

    public const ARCHIVE_PURCHASE_ORDER = 132;

    public const DELETE_PURCHASE_ORDER = 133;

    public const RESTORE_PURCHASE_ORDER = 134;

    public const EMAIL_PURCHASE_ORDER = 135;

    public const VIEW_PURCHASE_ORDER = 136;

    public const ACCEPT_PURCHASE_ORDER = 137;

    public const PAYMENT_EMAILED = 138;

    public const VENDOR_NOTIFICATION_EMAIL = 139;

    public const EMAIL_STATEMENT = 140;

    public const USER_NOTE = 141;

    public const QUOTE_REMINDER1_SENT = 142;

    public const AUTOBILL_SUCCESS = 143;

    public const AUTOBILL_FAILURE = 144;

    public const EINVOICE_SENT = 145;

    public const EINVOICE_DELIVERY_SUCCESS = 146;

    public const EINVOICE_DELIVERY_FAILURE = 147;

    public const E_EXPENSE_CREATED = 148;

    public const EMAIL_CREDIT = 149;
    
    protected $casts = [
        'is_system' => 'boolean',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $appends = [
        'hashed_id',
    ];

    protected $with = [
        'backup',
    ];

    public function getHashedIdAttribute(): string
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function backup(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Backup::class);
    }

    public function history(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Backup::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id', 'id')->withTrashed();
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }


    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }


    public function recurring_invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class)->withTrashed();
    }

    public function credit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Credit::class)->withTrashed();
    }

    public function quote(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Quote::class)->withTrashed();
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class)->withTrashed();
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Payment::class)->withTrashed();
    }

    public function expense(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Expense::class)->withTrashed();
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function recurring_expense(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class)->withTrashed();
    }

    public function purchase_order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    public function vendor_contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VendorContact::class)->withTrashed();
    }

    public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function activity_string()
    {
        $intersect = [
            ':invoice',
            ':client',
            ':contact',
            ':user',
            ':vendor',
            ':quote',
            ':credit',
            ':payment',
            ':task',
            ':expense',
            ':purchase_order',
            ':subscription',
            ':recurring_invoice',
            ':recurring_expense',
            ':amount',
            ':balance',
            ':number',
            ':payment_amount',
            ':gateway',
            ':adjustment'
        ];

        $found_variables = array_intersect(explode(" ", trans("texts.activity_{$this->activity_type_id}")), $intersect);

        if ($this->activity_type_id == 10 && $this->client_contact_id && !$this->token_id) {
            $found_variables = array_intersect(explode(" ", trans("texts.activity_10_online")), $intersect);
        }

        if ($this->activity_type_id == 54 && !$this->token_id) {
            array_push($found_variables, ':contact');
        }

        $replacements = [];

        foreach ($found_variables as $var) {
            $replacements = array_merge($replacements, $this->matchVar($var));
        }

        if ($this->client) {
            $replacements['client'] = ['label' => $this->client?->present()->name() ?? '', 'hashed_id' => $this->client->hashed_id ?? ''];
        }

        if ($this->vendor) {
            $replacements['vendor'] = ['label' => $this->vendor?->present()->name() ?? '', 'hashed_id' => $this->vendor->hashed_id ?? ''];
        }

        if ($this->activity_type_id == 4 && $this->recurring_invoice) {
            $replacements['recurring_invoice'] = ['label' => $this?->recurring_invoice?->number ?? '', 'hashed_id' => $this->recurring_invoice->hashed_id ?? ''];
        }

        $replacements['activity_type_id'] = $this->activity_type_id;
        $replacements['id'] = $this->id;
        $replacements['hashed_id'] = $this->hashed_id;
        $replacements['notes'] = $this->notes ?? '';
        $replacements['created_at'] = $this->created_at ?? '';
        $replacements['ip'] = $this->ip ?? '';

        if ($this->activity_type_id == 141) {
            $replacements = $this->harvestNoteEntities($replacements);
        }

        return $replacements;

    }

    private function harvestNoteEntities(array $replacements): array
    {
        $entities = [
            ':invoice',
            ':quote',
            ':credit',
            ':payment',
            ':task',
            ':expense',
            ':purchase_order',
            ':recurring_invoice',
            ':recurring_expense',
            ':client',

        ];

        foreach ($entities as $entity) {
            $entity_key = substr($entity, 1);

            if ($this?->{$entity_key}) {
                $replacements = array_merge($replacements, $this->matchVar($entity));
            }

        }

        return $replacements;
    }

    private function matchVar(string $variable)
    {
        $system = ctrans('texts.system');

        $translation = '';

        match($variable) {
            ':invoice' => $translation = [substr($variable, 1) => [ 'label' => $this?->invoice?->number ?? '', 'hashed_id' => $this->invoice?->hashed_id ?? '']],
            ':user' => $translation =  [substr($variable, 1) => [ 'label' => $this?->user?->present()->name() ?? $system, 'hashed_id' => $this->user->hashed_id ?? '']],
            ':quote' => $translation =  [substr($variable, 1) => [ 'label' => $this?->quote?->number ?? '', 'hashed_id' => $this->quote->hashed_id ?? '']],
            ':credit' => $translation =  [substr($variable, 1) => [ 'label' => $this?->credit?->number ?? '', 'hashed_id' => $this->credit->hashed_id ?? '']],
            ':payment' => $translation =  [substr($variable, 1) => [ 'label' => $this?->payment?->number ?? '', 'hashed_id' => $this->payment->hashed_id ?? '']],
            ':task' => $translation =  [substr($variable, 1) => [ 'label' => $this?->task?->number ?? '', 'hashed_id' => $this->task->hashed_id ?? '']],
            ':expense' => $translation =  [substr($variable, 1) => [ 'label' => $this?->expense?->number ?? '', 'hashed_id' => $this->expense->hashed_id ?? '']],
            ':purchase_order' => $translation =  [substr($variable, 1) => [ 'label' => $this?->purchase_order?->number ?? '', 'hashed_id' => $this->purchase_order->hashed_id ?? '']],
            ':subscription' => $translation =  [substr($variable, 1) => [ 'label' => $this?->subscription?->number ?? '', 'hashed_id' => $this->subscription->hashed_id ?? '' ]],
            ':recurring_invoice' => $translation =  [substr($variable, 1) => [ 'label' =>  $this?->recurring_invoice?->number ?? '', 'hashed_id' => $this->recurring_invoice->hashed_id ?? '']],
            ':recurring_expense' => $translation =  [substr($variable, 1) => [ 'label' => $this?->recurring_expense?->number ?? '', 'hashed_id' => $this->recurring_expense->hashed_id ?? '']],
            ':payment_amount' => $translation =  [substr($variable, 1) => [ 'label' =>  Number::formatMoney($this?->payment?->amount, $this?->payment?->client ?? $this->company) ?? '', 'hashed_id' => '']],
            ':adjustment' => $translation =  [substr($variable, 1) => [ 'label' =>  Number::formatMoney($this?->payment?->refunded, $this?->payment?->client ?? $this->company) ?? '', 'hashed_id' => '']],
            ':ip' => $translation = [ 'ip' => $this->ip ?? ''],
            ':contact' => $translation = $this->resolveContact(),
            ':notes' => $translation = [ 'notes' => $this->notes ?? ''],

            default => $translation = [],
        };

        return $translation;
    }

    private function resolveContact(): array
    {
        $contact = $this->contact ? $this->contact : $this->vendor_contact;

        $entity = $this->contact ? $this->client : $this->vendor;

        $contact_entity = $this->contact ? 'clients' : 'vendors';

        if (!$contact) {
            return [];
        }

        return ['contact' => [ 'label' => $contact?->present()->name() ?? '', 'hashed_id' => $entity->hashed_id ?? '', 'contact_entity' => $contact_entity]];
    }
}
