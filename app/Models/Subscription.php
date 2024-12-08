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

use App\Services\Subscription\PaymentLinkService;
use App\Services\Subscription\SubscriptionCalculator;
use App\Services\Subscription\SubscriptionService;
use App\Services\Subscription\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Subscription
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int $remaining_cycles
 * @property string|null $product_ids
 * @property int|null $frequency_id
 * @property string|null $auto_bill
 * @property string|null $promo_code
 * @property float $promo_discount
 * @property bool $is_amount_discount
 * @property int $allow_cancellation
 * @property int $per_seat_enabled
 * @property int $min_seats_limit
 * @property int $max_seats_limit
 * @property int $trial_enabled
 * @property int $trial_duration
 * @property int $allow_query_overrides
 * @property int $allow_plan_changes
 * @property string|null $plan_map
 * @property int|null $refund_period
 * @property array $webhook_configuration
 * @property int|null $deleted_at
 * @property bool $is_deleted
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $currency_id
 * @property string|null $recurring_product_ids
 * @property string $name
 * @property int|null $group_id
 * @property float $price
 * @property float $promo_price
 * @property int $registration_required
 * @property int $use_inventory_management
 * @property string|null $optional_product_ids
 * @property string|null $optional_recurring_product_ids
 * @property-read \App\Models\Company $company
 * @property-read mixed $hashed_id
 * @property-read \App\Models\GroupSetting|null $group_settings
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\SubscriptionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription withoutTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription with($value)
 * @mixin \Eloquent
 */
class Subscription extends BaseModel
{
    use HasFactory;
    use SoftDeletes;
    use Filterable;

    protected $hidden = [
        'id',
        'user_id',
        'assigned_user_id',
        'company_id',
        'product_ids',
        'recurring_product_ids',
        'group_id',
    ];

    protected $fillable = [
        'assigned_user_id',
        'product_ids',
        'recurring_product_ids',
        'frequency_id',
        'auto_bill',
        'promo_code',
        'promo_discount',
        'is_amount_discount',
        'allow_cancellation',
        'per_seat_enabled',
        'max_seats_limit',
        'trial_enabled',
        'trial_duration',
        'allow_query_overrides',
        'allow_plan_changes',
        'refund_period',
        'webhook_configuration',
        'currency_id',
        'group_id',
        'price',
        'name',
        'currency_id',
        'registration_required',
        'optional_product_ids',
        'optional_recurring_product_ids',
        'use_inventory_management',
        'steps',
        'remaining_cycles',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'webhook_configuration' => 'array',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'trial_enabled' => 'boolean',
        'allow_plan_changes' => 'boolean',
    ];

    protected $with = [
        'company',
    ];

    public function service(): SubscriptionService
    {
        return new SubscriptionService($this);
    }

    public function link_service(): PaymentLinkService
    {
        return new PaymentLinkService($this);
    }

    public function status(RecurringInvoice $recurring_invoice): SubscriptionStatus
    {
        return (new SubscriptionStatus($this, $recurring_invoice))->run();
    }

    public function calc(): SubscriptionCalculator
    {
        return new SubscriptionCalculator($this);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function group_settings()
    {
        return $this->belongsTo(GroupSetting::class, 'group_id', 'id');
    }

    public function nextDateByInterval($date, $frequency_id)
    {
        switch ($frequency_id) {
            case RecurringInvoice::FREQUENCY_DAILY:
                return $date->addDay();
            case RecurringInvoice::FREQUENCY_WEEKLY:
                return $date->addWeek();
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                return $date->addWeeks(2);
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                return $date->addWeeks(4);
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return $date->addMonthNoOverflow();
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return $date->addMonthsNoOverflow(2);
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return $date->addMonthsNoOverflow(3);
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return $date->addMonthsNoOverflow(4);
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return $date->addMonthsNoOverflow(6);
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return $date->addYear();
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return $date->addYears(2);
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return $date->addYears(3);
            default:
                return null;
        }
    }

    /**
     * Calculates the maximum product quantity available
     *
     * @param  mixed $product
     * @return int
     */
    public function maxQuantity(mixed $product): int
    {
        $max_quantity = data_get($product, 'max_quantity', 0);
        $in_stock_quantity = data_get($product, 'in_stock_quantity', 0);
        $max_limit = 100;

        if (!$this->use_inventory_management) {
            return min($max_limit, $max_quantity > 0 ? $max_quantity : $max_limit);
        }

        if ($max_quantity > 0) {
            return min($max_limit, $max_quantity);
        }

        return min($max_limit, $in_stock_quantity);
    }
}
