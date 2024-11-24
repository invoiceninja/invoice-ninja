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

use App\Utils\Traits\Inviteable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\RecurringInvoiceInvitation
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $client_contact_id
 * @property int $recurring_invoice_id
 * @property string $key
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $transaction_reference
 * @property string|null $message_id
 * @property string|null $email_error
 * @property string|null $signature_base64
 * @property string|null $signature_date
 * @property string|null $sent_date
 * @property string|null $viewed_date
 * @property string|null $opened_date
 * @property string|null $email_status
 * @property \App\Models\Company $company
 * @property \App\Models\ClientContact $contact
 * @property string $hashed_id
 * @property \App\Models\RecurringInvoice $recurring_invoice
 * @property \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereClientContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereEmailError($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereEmailStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereOpenedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereRecurringInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereSignatureBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereSignatureDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation whereViewedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringInvoiceInvitation withoutTrashed()
 * @mixin \Eloquent
 */
class RecurringInvoiceInvitation extends BaseModel
{
    use SoftDeletes;
    use Inviteable;

    protected $fillable = ['client_contact_id'];

    protected $touches = ['recurring_invoice'];

    protected $with = [
        'company',
        'contact',
    ];

    public function getEntityType()
    {
        return self::class;
    }


    public function getEntityString(): string
    {
        return 'recurring_invoice';
    }

    public function entityType()
    {
        return RecurringInvoice::class;
    }

    /**
     * @return mixed
     */
    public function recurring_invoice()
    {
        return $this->belongsTo(RecurringInvoice::class)->withTrashed();
    }

    /**
     * @return mixed
     */
    public function entity()
    {
        return $this->belongsTo(RecurringInvoice::class)->withTrashed();
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id', 'id')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function markViewed()
    {
        $this->viewed_date = now();
        $this->save();
    }

    public function markOpened()
    {
        $this->opened_date = now();
        $this->save();
    }
}
