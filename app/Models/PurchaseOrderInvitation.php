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

use App\Utils\Ninja;
use App\Utils\Traits\Inviteable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * App\Models\PurchaseOrderInvitation
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $vendor_contact_id
 * @property int $purchase_order_id
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
 * @property string|null $email_status
 * @property \App\Models\Company $company
 * @property \App\Models\VendorContact $contact
 * @property string $hashed_id
 * @property \App\Models\PurchaseOrder $purchase_order
 * @property \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\PurchaseOrderInvitationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereEmailError($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereEmailStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereOpenedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation wherePurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereSentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereSignatureBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereSignatureDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereVendorContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation whereViewedDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrderInvitation withoutTrashed()
 * @mixin \Eloquent
 */
class PurchaseOrderInvitation extends BaseModel
{
    use SoftDeletes;
    use Inviteable;

    protected $fillable = [
        'id',
        'vendor_contact_id',
    ];

    protected $with = [
        'company',
        'contact',
    ];

    protected $touches = ['purchase_order'];

    public function getEntityType()
    {
        return self::class;
    }

    public function getEntityString(): string
    {
        return 'purchase_order';
    }

    public function entityType()
    {
        return PurchaseOrder::class;
    }

    public function purchase_order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    public function entity(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VendorContact::class, 'vendor_contact_id', 'id')->withTrashed();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
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

    public function getPortalLink(): string
    {
        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        switch ($this->company->portal_mode) {
            case 'subdomain':
                return $domain.'/vendor/';
            case 'iframe':
                return $domain.'/vendor/';
            case 'domain':
                return $domain.'/vendor/';

            default:
                return '';
        }
    }

    public function getLink(): string
    {
        $entity_type = Str::snake(class_basename($this->entityType()));

        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        switch ($this->company->portal_mode) {
            case 'subdomain':
                return $domain.'/vendor/'.$entity_type.'/'.$this->key;
            case 'iframe':
                return $domain.'/vendor/'.$entity_type.'/'.$this->key;
            case 'domain':
                return $domain.'/vendor/'.$entity_type.'/'.$this->key;

            default:
                return '';
        }
    }

    public function getAdminLink($use_react_link = false): string
    {
        return $use_react_link ? $this->getReactLink() : $this->getLink().'?silent=true';
    }

    private function getReactLink(): string
    {
        $entity_type = Str::snake(class_basename($this->entityType()));

        return config('ninja.react_url')."/#/{$entity_type}s/{$this->{$entity_type}->hashed_id}/edit";
    }

}
