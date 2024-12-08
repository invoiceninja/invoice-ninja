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
 * App\Models\QuoteInvitation
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $client_contact_id
 * @property int $quote_id
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
 * @property \App\Models\Quote $quote
 * @property \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\QuoteInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|QuoteInvitation withoutTrashed()
 * @mixin \Eloquent
 */
class QuoteInvitation extends BaseModel
{
    use Inviteable;
    use SoftDeletes;

    protected $fillable = [
        'id',
        'client_contact_id',
    ];

    protected $with = [
        'company',
        'contact',
    ];

    protected $touches = ['quote'];

    public function getEntityType()
    {
        return self::class;
    }

    public function getEntityString(): string
    {
        return 'quote';
    }

    public function entityType()
    {
        return Quote::class;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function quote(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Quote::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function entity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Quote::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'client_contact_id', 'id')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
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

    public function markViewed()
    {
        $this->viewed_date = Carbon::now();
        $this->save();
    }

}
