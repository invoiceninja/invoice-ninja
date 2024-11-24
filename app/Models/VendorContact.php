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

use Laravel\Scout\Searchable;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Presenters\VendorContactPresenter;
use App\Notifications\ClientContactResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Translation\HasLocalePreference;

/**
 * App\Models\VendorContact
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $vendor_id
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property bool $is_primary
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property bool $send_email
 * @property string|null $email_verified_at
 * @property string|null $confirmation_code
 * @property bool $confirmed
 * @property string|null $last_login
 * @property int|null $failed_logins
 * @property string|null $oauth_user_id
 * @property int|null $oauth_provider_id
 * @property string|null $google_2fa_secret
 * @property string|null $accepted_terms_version
 * @property string|null $avatar
 * @property string|null $avatar_type
 * @property string|null $avatar_size
 * @property string $password
 * @property string|null $token
 * @property bool $is_locked
 * @property string|null $contact_key
 * @property string|null $remember_token
 * @property-read \App\Models\Company $company
 * @property-read mixed $contact_id
 * @property-read mixed $hashed_id
 * @property-read int|null $notifications_count
 * @property-read int|null $purchase_order_invitations_count
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor $vendor
 * @method static \Database\Factories\VendorContactFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact query()
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|VendorContact withoutTrashed()
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrderInvitation> $purchase_order_invitations
 * @mixin \Eloquent
 */
class VendorContact extends Authenticatable implements HasLocalePreference
{
    use Notifiable;
    use MakesHash;
    use PresentableTrait;
    use SoftDeletes;
    use HasFactory;
    use Searchable;
    
    /* Used to authenticate a vendor */
    protected $guard = 'vendor';

    protected $touches = ['vendor'];

    protected $presenter = VendorContactPresenter::class;

    /* Allow microtime timestamps */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $appends = [
        'hashed_id',
    ];

    protected $with = [];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'last_login' => 'timestamp',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'email',
        'is_primary',
        'vendor_id',
        'send_email',
    ];

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->present()->search_display(),
            'hashed_id' => $this->vendor ->hashed_id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'custom_value1' => $this->custom_value1,
            'custom_value2' => $this->custom_value2,
            'custom_value3' => $this->custom_value3,
            'custom_value4' => $this->custom_value4,
            'company_key' => $this->company->company_key,
        ];
    }

    public function getScoutKey()
    {
        return $this->hashed_id;
    }

    public function avatar()
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        return asset('images/svg/user.svg');
    }

    public function setAvatarAttribute($value)
    {
        if (! filter_var($value, FILTER_VALIDATE_URL) && $value) {
            $this->attributes['avatar'] = url('/').$value;
        } else {
            $this->attributes['avatar'] = $value;
        }
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function getContactIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function primary_contact()
    {
        return $this->where('is_primary', true);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function sendPasswordResetNotification($token)
    {
        // $this->notify(new ClientContactResetPassword($token));
    }

    public function preferredLocale()
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Language> */
        $languages = app('languages');

        return $languages->first(function ($item) {
            return $item->id == $this->company->getSetting('language_id');
        })->locale ?? 'en';
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param mixed $value
     * @param null $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this
            ->withTrashed()
            ->where('id', $this->decodePrimaryKey($value))
            ->firstOrFail();
    }

    public function purchase_order_invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrderInvitation::class);
    }

    public function getLoginLink()
    {
        $domain = isset($this->company->portal_domain) ? $this->company->portal_domain : $this->company->domain();

        return $domain.'/vendor/key_login/'.$this->contact_key;
    }

    public function getAdminLink($use_react_link = false): string
    {
        return $use_react_link ? $this->getReactLink() : config('ninja.app_url');
    }

    private function getReactLink(): string
    {
        return config('ninja.react_url')."/#/vendors/{$this->vendor->hashed_id}";
    }

}
