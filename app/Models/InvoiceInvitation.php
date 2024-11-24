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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\InvoiceInvitation
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int|null $client_contact_id
 * @property int|null $invoice_id
 * @property string $key
 * @property string|null $transaction_reference
 * @property string|null $message_id
 * @property string|null $email_error
 * @property string|null $signature_base64
 * @property string|null $signature_date
 * @property string|null $sent_date
 * @property string|null $viewed_date
 * @property string|null $opened_date
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $signature_ip
 * @property string|null $email_status
 * @property \App\Models\Company $company
 * @property \App\Models\ClientContact $contact
 * @property mixed $hashed_id
 * @property \App\Models\Invoice $invoice
 * @property \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\InvoiceInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereClientContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereEmailError($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereEmailStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereOpenedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereSignatureBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereSignatureDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereSignatureIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation whereViewedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation withoutTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|InvoiceInvitation where()
 * @mixin \Eloquent
 */
class InvoiceInvitation extends BaseModel
{
    use SoftDeletes;
    use Inviteable;

    protected $fillable = [
        'client_contact_id',
    ];

    protected $with = [
        'company',
        'contact',
    ];

    protected $touches = ['invoice'];

    public function getEntityType()
    {
        return self::class;
    }

    public function getEntityString(): string
    {
        return 'invoice';
    }

    public function entityType()
    {
        return Invoice::class;
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function getEntity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id', 'id')->withTrashed();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function signatureDiv()
    {
        if (! $this->signature_base64) {
            return false;
        }

        return sprintf('<img src="data:image/svg+xml;base64,%s"></img><p/>%s: %s', $this->signature_base64, ctrans('texts.signed'), $this->createClientDate($this->signature_date, $this->contact->client->timezone()->name));
    }

    public function getName(): string
    {
        return $this->key;
    }

    public function markViewed(): void
    {
        $this->viewed_date = Carbon::now();
        $this->save();
    }

    public function markOpened(): void
    {
        $this->opened_date = Carbon::now();
        $this->save();
    }

}
