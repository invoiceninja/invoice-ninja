<?php
/**
 * Invoice Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\Utils\Traits\Inviteable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\RecurringQuoteInvitation
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $client_contact_id
 * @property int $recurring_quote_id
 * @property string $key
 * @property string|null $transaction_reference
 * @property string|null $message_id
 * @property string|null $email_error
 * @property string|null $signature_base64
 * @property string|null $signature_date
 * @property string|null $sent_date
 * @property string|null $viewed_date
 * @property string|null $opened_date
 * @property string|null $email_status
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\ClientContact $contact
 * @property-read mixed $hashed_id
 * @property-read \App\Models\RecurringQuote $recurring_quote
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereClientContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereEmailError($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereEmailStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereOpenedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereRecurringQuoteId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereSignatureBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereSignatureDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation whereViewedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuoteInvitation withoutTrashed()
 * @mixin \Eloquent
 */
class RecurringQuoteInvitation extends BaseModel
{
    use SoftDeletes;
    use Inviteable;

    protected $fillable = ['client_contact_id'];

    protected $touches = ['recurring_quote'];

    protected $with = [
        'company',
        'contact',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    public function entityType()
    {
        return RecurringQuote::class;
    }

    /**
     * @return mixed
     */
    public function recurring_quote()
    {
        return $this->belongsTo(RecurringQuote::class)->withTrashed();
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
