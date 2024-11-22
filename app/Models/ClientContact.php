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
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use App\Jobs\Mail\NinjaMailer;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\MakesHash;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Presenters\ClientContactPresenter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use App\Mail\ClientContact\ClientContactResetPasswordObject;

/**
 * Class ClientContact
 *
 * @method scope() static
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property int $user_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $email
 * @property string|null $email_verified_at
 * @property string|null $confirmation_code
 * @property bool $is_primary
 * @property bool $confirmed
 * @property int|null $last_login
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
 * @property bool $send_email
 * @property string|null $contact_key
 * @property string|null $remember_token
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property-read \App\Models\Client $client
 * @property-read \App\Models\Company $company
 * @property-read int|null $credit_invitations_count
 * @property-read mixed $contact_id
 * @property-read mixed $hashed_id
 * @property-read int|null $invoice_invitations_count
 * @property-read int|null $notifications_count
 * @property-read int|null $quote_invitations_count
 * @property-read int|null $recurring_invoice_invitations_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\ClientContactFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact query()
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|ClientContact withoutTrashed()
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuoteInvitation> $quote_invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoiceInvitation> $recurring_invoice_invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CreditInvitation> $credit_invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceInvitation> $invoice_invitations
 * @mixin \Eloquent
 */
class ClientContact extends Authenticatable implements HasLocalePreference
{
    use Notifiable;
    use MakesHash;
    use PresentableTrait;
    use SoftDeletes;
    use HasFactory;
    use AppSetup;

    use Searchable;

    /* Used to authenticate a contact */
    protected $guard = 'contact';

    protected $touches = ['client'];

    /* Allow microtime timestamps */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $presenter = ClientContactPresenter::class;

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

    protected $hidden = [
        'password',
        'remember_token',
        'user_id',
        'company_id',
        'client_id',
        'google_2fa_secret',
        'id',
        'oauth_provider_id',
        'oauth_user_id',
        'token',
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
        'send_email',
    ];

    /**
     * Whitelisted fields for using from query parameters on subscriptions request.
     *
     * @var string[]
     */
    public static $subscription_fillable = [
        'first_name',
        'last_name',
        'phone',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'email',
    ];

    public function toSearchableArray()
    {
        return [
            'name' => $this->present()->search_display(),
            'hashed_id' => $this->client->hashed_id,
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

    public function getScoutKeyName()
    {
        return 'hashed_id';
    }
    /*
    V2 type of scope
     */
    public function scopeCompany($query)
    {
        $query->where('company_id', auth()->user()->companyId());

        return $query;
    }

    /*
     V1 type of scope
     */
    public function scopeScope($query)
    {
        $query->where($this->getTable().'.company_id', '=', auth()->user()->company()->id);

        return $query;
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    /**/
    public function getRouteKeyName()
    {
        return 'contact_id';
    }

    public function getContactIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function setAvatarAttribute($value)
    {
        if (! filter_var($value, FILTER_VALIDATE_URL) && $value) {
            $this->attributes['avatar'] = url('/').$value;
        } else {
            $this->attributes['avatar'] = $value;
        }
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
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

    public function invoice_invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvoiceInvitation::class);
    }

    public function recurring_invoice_invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RecurringInvoiceInvitation::class);
    }

    public function quote_invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuoteInvitation::class);
    }

    public function credit_invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CreditInvitation::class);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->token = $token;
        $this->save();

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new NinjaMailer((new ClientContactResetPasswordObject($token, $this))->build());
        $nmo->to_user = $this;
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;

        NinjaMailerJob::dispatch($nmo);
    }

    public function preferredLocale()
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Language> */
        $languages = app('languages');

        return $languages->first(function ($item) {
            return $item->id == $this->client->getSetting('language_id');
        })->locale ?? 'en';
    }

    public function routeNotificationForMail($notification)
    {
        return $this->email;
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
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }

    /**
     * @return mixed|string
     */
    public function avatar()
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        return asset('images/svg/user.svg');
    }

    /**
     * Provides a convenience login click for contacts to bypass the
     * contact authentication layer
     *
     * @return string URL
     */
    public function getLoginLink()
    {

        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        switch ($this->company->portal_mode) {
            case 'subdomain':
                return $domain.'/client/key_login/'.$this->contact_key;
            case 'iframe':
                return $domain.'/client/key_login/'.$this->contact_key;
            case 'domain':
                return $domain.'/client/key_login/'.$this->contact_key;

            default:
                return '';
        }
    }

    public function getAdminLink($use_react_link = false): string
    {
        return $use_react_link ? $this->getReactLink() : config('ninja.app_url');
    }

    private function getReactLink(): string
    {
        return config('ninja.react_url')."/#/clients/{$this->client->hashed_id}";
    }

    public function showRff(): bool
    {
        // if (\strlen($this->first_name ?? '') === 0 || \strlen($this->last_name ?? '') === 0 || \strlen($this->email ?? '') === 0) {
        //     return true;
        // }

        return false;
    }
}
