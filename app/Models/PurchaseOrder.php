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
use App\Utils\Number;
use Laravel\Scout\Searchable;
use Illuminate\Support\Carbon;
use App\Helpers\Invoice\InvoiceSum;
use Illuminate\Support\Facades\App;
use App\Helpers\Invoice\InvoiceSumInclusive;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\PurchaseOrder\PurchaseOrderService;
use App\Events\PurchaseOrder\PurchaseOrderWasEmailed;

/**
 * App\Models\PurchaseOrder
 *
 * @property int $id
 * @property object|null $e_invoice
 * @property int|null $client_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int $status_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property int|null $recurring_id
 * @property int|null $design_id
 * @property int|null $invoice_id
 * @property string|null $number
 * @property float $discount
 * @property bool $is_amount_discount
 * @property string|null $po_number
 * @property string|null $date
 * @property string|null $last_sent_date
 * @property string|null $due_date
 * @property bool $is_deleted
 * @property object|array|string $line_items
 * @property object|null $backup
 * @property string|null $footer
 * @property string|null $public_notes
 * @property string|null $private_notes
 * @property string|null $terms
 * @property string|null $tax_name1
 * @property float $tax_rate1
 * @property string|null $tax_name2
 * @property float $tax_rate2
 * @property string|null $tax_name3
 * @property float $tax_rate3
 * @property float $total_taxes
 * @property bool $uses_inclusive_taxes
 * @property string|null $reminder1_sent
 * @property string|null $reminder2_sent
 * @property string|null $reminder3_sent
 * @property string|null $reminder_last_sent
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $next_send_date
 * @property string|null $custom_surcharge1
 * @property string|null $custom_surcharge2
 * @property string|null $custom_surcharge3
 * @property string|null $custom_surcharge4
 * @property int $custom_surcharge_tax1
 * @property int $custom_surcharge_tax2
 * @property int $custom_surcharge_tax3
 * @property int $custom_surcharge_tax4
 * @property float $exchange_rate
 * @property float $balance
 * @property float|null $partial
 * @property float $amount
 * @property float $paid_to_date
 * @property string|null $partial_due_date
 * @property string|null $last_viewed
 * @property int|null $deleted_at
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $expense_id
 * @property int|null $currency_id
 * @property-read int|null $activities_count
 * @property \App\Models\User|null $assigned_user
 * @property \App\Models\Client|null $client
 * @property \App\Models\Company $company
 * @property-read int|null $documents_count
 * @property \App\Models\Expense|null $expense
 * @property string $hashed_id
 * @property \App\Models\Invoice|null $invoice
 * @property \App\Models\Project|null $project
 * @property \App\Models\User $user
 * @property \App\Models\Vendor $vendor
 * @property \App\Models\PurchaseOrderInvitation $invitation
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder exclude($columns)
 * @method static \Database\Factories\PurchaseOrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrderInvitation> $invitations
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|PurchaseOrder withoutTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @mixin \Eloquent
 */
class PurchaseOrder extends BaseModel
{
    use Filterable;
    use SoftDeletes;
    use Searchable;
    
    protected $hidden = [
        'id',
        'private_notes',
        'user_id',
        'vendor_id',
        'company_id',
    ];

    protected $fillable = [
        'number',
        'discount',
        'status_id',
        'last_sent_date',
        'is_deleted',
        'po_number',
        'date',
        'due_date',
        'terms',
        'public_notes',
        'private_notes',
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',
        'tax_name3',
        'tax_rate3',
        'total_taxes',
        'uses_inclusive_taxes',
        'is_amount_discount',
        'partial',
        'recurring_id',
        'next_send_date',
        'reminder1_sent',
        'reminder2_sent',
        'reminder3_sent',
        'reminder_last_sent',
        'partial_due_date',
        'project_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'backup',
        'footer',
        'line_items',
        'client_id',
        'custom_surcharge1',
        'custom_surcharge2',
        'custom_surcharge3',
        'custom_surcharge4',
        'design_id',
        'invoice_id',
        'assigned_user_id',
        'exchange_rate',
        'balance',
        'partial',
        'paid_to_date',
        'vendor_id',
        'last_viewed',
        'currency_id',
    ];

    protected $casts = [
        'line_items' => 'object',
        'backup' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'is_amount_discount' => 'bool',
        'e_invoice' => 'object',
    ];

    public const STATUS_DRAFT = 1;
    public const STATUS_SENT = 2;
    public const STATUS_ACCEPTED = 3;
    public const STATUS_RECEIVED = 4;
    public const STATUS_CANCELLED = 5;


    public function toSearchableArray()
    {
        $locale = $this->company->locale();
        App::setLocale($locale);

        return [
            'id' => $this->id,
            'name' => ctrans('texts.purchase_order') . " " . $this->number . " | " . $this->vendor->present()->name() .  ' | ' . Number::formatMoney($this->amount, $this->company) . ' | ' . $this->translateDate($this->date, $this->company->date_format(), $locale),
            'hashed_id' => $this->hashed_id,
            'number' => $this->number,
            'is_deleted' => $this->is_deleted,
            'amount' => (float) $this->amount,
            'balance' => (float) $this->balance,
            'due_date' => $this->due_date,
            'date' => $this->date,
            'custom_value1' => (string)$this->custom_value1,
            'custom_value2' => (string)$this->custom_value2,
            'custom_value3' => (string)$this->custom_value3,
            'custom_value4' => (string)$this->custom_value4,
            'company_key' => $this->company->company_key,
            'po_number' => (string)$this->po_number,
        ];
    }

    public function getScoutKey()
    {
        return $this->hashed_id;
    }
    
    public static function stringStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return ctrans('texts.draft');
            case self::STATUS_SENT:
                return ctrans('texts.sent');
            case self::STATUS_ACCEPTED:
                return ctrans('texts.accepted');
            case self::STATUS_CANCELLED:
                return ctrans('texts.cancelled');
            default:
                return ctrans('texts.sent');

        }
    }

    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return '<h5><span class="badge badge-light">'.ctrans('texts.draft').'</span></h5>';
            case self::STATUS_SENT:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h5>';
            case self::STATUS_ACCEPTED:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.accepted').'</span></h5>';
            case self::STATUS_CANCELLED:
                return '<h5><span class="badge badge-secondary">'.ctrans('texts.cancelled').'</span></h5>';
            default:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h5>';
        }
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function assigned_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function history(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Activity::class)->where('company_id', $this->company_id)->where('vendor_id', $this->vendor_id)->orderBy('id', 'DESC')->take(50);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function expense(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Expense::class)->withTrashed();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function markInvitationsSent(): void
    {
        $this->invitations->each(function ($invitation) {
            if (! isset($invitation->sent_date)) {
                $invitation->sent_date = Carbon::now();
                $invitation->saveQuietly();
            }
        });
    }

    public function invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrderInvitation::class);
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return PurchaseOrderService  */
    public function service(): PurchaseOrderService
    {
        return new PurchaseOrderService($this);
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Invoice::class)->using(Paymentable::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(Payment::class, 'paymentable');
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Access the invoice calculator object.
     *
     * @return InvoiceSumInclusive | InvoiceSum The invoice calculator object getters
     */
    public function calc(): InvoiceSumInclusive | InvoiceSum
    {
        $purchase_order_calc = null;

        if ($this->uses_inclusive_taxes) {
            $purchase_order_calc = new InvoiceSumInclusive($this);
        } else {
            $purchase_order_calc = new InvoiceSum($this);
        }

        return $purchase_order_calc->build();
    }

    public function translate_entity(): string
    {
        return ctrans('texts.purchase_order');
    }

    // public function typeIdString($id): string
    // {
    //     $type = '';
    //     match($id) {
    //         '1' => $type = ctrans('texts.product'),
    //         '2' => $type = ctrans('texts.service'),
    //         '3' => $type = ctrans('texts.gateway_fees'),
    //         '4' => $type = ctrans('texts.gateway_fees'),
    //         '5' => $type = ctrans('texts.late_fees'),
    //         '6' => $type = ctrans('texts.expense'),
    //         default => $type = ctrans('texts.product'),
    //     };

    //     return $type;

    // }

    public function taxTypeString($id): string
    {
        $tax_type = '';

        match(intval($id)) {
            Product::PRODUCT_TYPE_PHYSICAL => $tax_type = ctrans('texts.physical_goods'),
            Product::PRODUCT_TYPE_SERVICE => $tax_type = ctrans('texts.services'),
            Product::PRODUCT_TYPE_DIGITAL => $tax_type = ctrans('texts.digital_products'),
            Product::PRODUCT_TYPE_SHIPPING => $tax_type = ctrans('texts.shipping'),
            Product::PRODUCT_TYPE_EXEMPT => $tax_type = ctrans('texts.tax_exempt'),
            Product::PRODUCT_TYPE_REDUCED_TAX => $tax_type = ctrans('texts.reduced_tax'),
            Product::PRODUCT_TYPE_OVERRIDE_TAX => $tax_type = ctrans('texts.override_tax'),
            Product::PRODUCT_TYPE_ZERO_RATED => $tax_type = ctrans('texts.zero_rated'),
            Product::PRODUCT_TYPE_REVERSE_TAX => $tax_type = ctrans('texts.reverse_tax'),
            default => $tax_type = ctrans('texts.physical_goods'),
        };

        return $tax_type;
    }


    public function entityEmailEvent($invitation, $reminder_template, $template = '')
    {
        
        switch ($reminder_template) {
            case 'purchase_order':
                event(new PurchaseOrderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $reminder_template));
                break;
                
            default:
                event(new PurchaseOrderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $reminder_template));
                break;
        }
    }
}
