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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\SystemLog
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property int|null $client_id
 * @property int|null $category_id
 * @property int|null $event_id
 * @property int|null $type_id
 * @property array $log
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog company()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog query()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereLog($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|SystemLog withoutTrashed()
 * @mixin \Eloquent
 */
class SystemLog extends Model
{
    use Filterable;
    use SoftDeletes;
    use MakesHash;

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'log' => 'array',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /* Category IDs */
    public const CATEGORY_GATEWAY_RESPONSE = 1;

    public const CATEGORY_MAIL = 2;

    public const CATEGORY_WEBHOOK = 3;

    public const CATEGORY_PDF = 4;

    public const CATEGORY_SECURITY = 5;

    public const CATEGORY_LOG = 6;

    /* Event IDs*/
    public const EVENT_PAYMENT_RECONCILIATION_FAILURE = 10;

    public const EVENT_PAYMENT_RECONCILIATION_SUCCESS = 11;

    public const EVENT_GATEWAY_SUCCESS = 21;

    public const EVENT_GATEWAY_FAILURE = 22;

    public const EVENT_GATEWAY_ERROR = 23;

    public const EVENT_MAIL_SEND = 30;

    public const EVENT_MAIL_RETRY_QUEUE = 31; //we use this to queue emails that are spooled and not sent due to the email queue quota being exceeded.

    public const EVENT_MAIL_BOUNCED = 32;

    public const EVENT_MAIL_SPAM_COMPLAINT = 33;

    public const EVENT_MAIL_DELIVERY = 34;

    public const EVENT_MAIL_OPENED = 35;

    public const EVENT_WEBHOOK_RESPONSE = 40;

    public const EVENT_WEBHOOK_SUCCESS = 41;

    public const EVENT_WEBHOOK_FAILURE = 42;

    public const EVENT_PDF_RESPONSE = 50;

    public const EVENT_AUTHENTICATION_FAILURE = 60;

    public const EVENT_USER = 61;

    public const EVENT_INBOUND_MAIL_BLOCKED = 62;

    /*Type IDs*/
    public const TYPE_PAYPAL = 300;

    public const TYPE_STRIPE = 301;

    public const TYPE_LEDGER = 302;

    public const TYPE_FAILURE = 303;

    public const TYPE_CHECKOUT = 304;

    public const TYPE_AUTHORIZE = 305;

    public const TYPE_CUSTOM = 306;

    public const TYPE_BRAINTREE = 307;

    public const TYPE_WEPAY = 309;

    public const TYPE_PAYFAST = 310;

    public const TYPE_PAYTRACE = 311;

    public const TYPE_MOLLIE = 312;

    public const TYPE_EWAY = 313;

    public const TYPE_FORTE = 314;

    public const TYPE_SQUARE = 320;

    public const TYPE_GOCARDLESS = 321;

    public const TYPE_RAZORPAY = 322;

    public const TYPE_PAYPAL_PPCP = 323;

    public const TYPE_BTC_PAY = 324;

    public const TYPE_ROTESSA = 325;

    public const TYPE_BLOCKONOMICS = 326;

    public const TYPE_POWERBOARD = 327;

    public const TYPE_QUOTA_EXCEEDED = 400;

    public const TYPE_UPSTREAM_FAILURE = 401;

    public const TYPE_WEBHOOK_RESPONSE = 500;

    public const TYPE_PDF_FAILURE = 600;

    public const TYPE_PDF_SUCCESS = 601;

    public const TYPE_MODIFIED = 701;

    public const TYPE_DELETED = 702;

    public const TYPE_LOGIN_SUCCESS = 800;

    public const TYPE_LOGIN_FAILURE = 801;

    public const TYPE_GENERIC = 900;

    protected $fillable = [
        'client_id',
        'company_id',
        'user_id',
        'log',
        'category_id',
        'event_id',
        'type_id',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Record with value {$value} not found");
        }

        return $this
            ->where('id', $this->decodePrimaryKey($value))
            ->company()
            ->firstOrFail();
    }

    /*
    V2 type of scope
     */
    public function scopeCompany($query)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $query->where('company_id', $user->companyId());

        return $query;
    }

    public function company()
    {
        return $this->hasMany(\App\Models\Company::class);
    }

    public function getCategoryName()
    {
        switch ($this->category_id) {
            case self::CATEGORY_GATEWAY_RESPONSE:
                return 'Gateway';
            case self::CATEGORY_MAIL:
                return 'Mail';
            case self::CATEGORY_WEBHOOK:
                return 'Webhook';
            case self::CATEGORY_PDF:
                return 'PDF';
            case self::CATEGORY_SECURITY:
                return 'Security';

            default:
                return 'undefined';
        }
    }

    public function getEventName()
    {
        switch ($this->event_id) {
            case self::EVENT_PAYMENT_RECONCILIATION_FAILURE:
                return 'Payment reco failure';
            case self::EVENT_PAYMENT_RECONCILIATION_SUCCESS:
                return 'Payment reco success';
            case self::EVENT_GATEWAY_SUCCESS:
                return 'Success';
            case self::EVENT_GATEWAY_FAILURE:
                return 'Failure';
            case self::EVENT_GATEWAY_ERROR:
                return 'Error';
            case self::EVENT_MAIL_SEND:
                return 'Send';
            case self::EVENT_MAIL_RETRY_QUEUE:
                return 'Retry';
            case self::EVENT_MAIL_BOUNCED:
                return 'Bounced';
            case self::EVENT_MAIL_SPAM_COMPLAINT:
                return 'Spam';
            case self::EVENT_MAIL_DELIVERY:
                return 'Delivery';
            case self::EVENT_WEBHOOK_RESPONSE:
                return 'Webhook Response';
            case self::EVENT_PDF_RESPONSE:
                return 'Pdf Response';
            case self::EVENT_AUTHENTICATION_FAILURE:
                return 'Auth Failure';
            case self::EVENT_USER:
                return 'User';
            default:
                return 'undefined';
        }
    }

    public function getTypeName()
    {
        switch ($this->type_id) {
            case self::TYPE_QUOTA_EXCEEDED:
                return 'Quota Exceeded';
            case self::TYPE_UPSTREAM_FAILURE:
                return 'Upstream Failure';
            case self::TYPE_WEBHOOK_RESPONSE:
                return 'Webhook';
            case self::TYPE_PDF_FAILURE:
                return 'Failure';
            case self::TYPE_PDF_SUCCESS:
                return 'Success';
            case self::TYPE_MODIFIED:
                return 'Modified';
            case self::TYPE_DELETED:
                return 'Deleted';
            case self::TYPE_LOGIN_SUCCESS:
                return 'Login Success';
            case self::TYPE_LOGIN_FAILURE:
                return 'Login Failure';
            case self::TYPE_PAYPAL:
                return 'PayPal';
            case self::TYPE_STRIPE:
                return 'Stripe';
            case self::TYPE_LEDGER:
                return 'Ledger';
            case self::TYPE_FAILURE:
                return 'Failure';
            case self::TYPE_CHECKOUT:
                return 'Checkout';
            case self::TYPE_AUTHORIZE:
                return 'Auth.net';
            case self::TYPE_CUSTOM:
                return 'Custom';
            case self::TYPE_BRAINTREE:
                return 'Braintree';
            case self::TYPE_WEPAY:
                return 'WePay';
            case self::TYPE_PAYFAST:
                return "Payfast";
            case self::TYPE_FORTE:
                return "Forte";
            default:
                return 'undefined';
        }
    }
}
