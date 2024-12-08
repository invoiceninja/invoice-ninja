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

use App\Utils\Number;
use Laravel\Scout\Searchable;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Expense
 *
 * @property int $id
 * @property object|null $e_invoice
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property int $company_id
 * @property int|null $vendor_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int|null $invoice_id
 * @property int|null $client_id
 * @property int|null $bank_id
 * @property int|null $invoice_currency_id
 * @property int|null $currency_id
 * @property int|null $category_id
 * @property int|null $payment_type_id
 * @property int|null $recurring_expense_id
 * @property bool $is_deleted
 * @property float $amount
 * @property float $foreign_amount
 * @property float $exchange_rate
 * @property string|null $tax_name1
 * @property float $tax_rate1
 * @property string|null $tax_name2
 * @property float $tax_rate2
 * @property string|null $tax_name3
 * @property float $tax_rate3
 * @property string|null $date
 * @property string|null $payment_date
 * @property string|null $private_notes
 * @property string|null $public_notes
 * @property string|null $transaction_reference
 * @property bool $should_be_invoiced
 * @property bool $invoice_documents
 * @property int|null $transaction_id
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $number
 * @property int|null $project_id
 * @property float $tax_amount1
 * @property float $tax_amount2
 * @property float $tax_amount3
 * @property bool $uses_inclusive_taxes
 * @property bool $calculate_tax_by_amount
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\ExpenseCategory|null $category
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Currency|null $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read \App\Models\PaymentType|null $payment_type
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\PurchaseOrder|null $purchase_order
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\ExpenseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Expense filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Expense newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Expense onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Expense query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Expense withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Expense withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\BankTransaction|null $transaction
 * @mixin \Eloquent
 */
class Expense extends BaseModel
{
    use SoftDeletes;
    use Filterable;
    use Searchable;
    
    protected $fillable = [
        'client_id',
        'assigned_user_id',
        'vendor_id',
        'invoice_id',
        'currency_id',
        'date',
        'invoice_currency_id',
        'amount',
        'foreign_amount',
        'exchange_rate',
        'private_notes',
        'public_notes',
        'bank_id',
        'transaction_id',
        'category_id',
        'tax_rate1',
        'tax_name1',
        'tax_rate2',
        'tax_name2',
        'tax_rate3',
        'tax_name3',
        'payment_date',
        'payment_type_id',
        'project_id',
        'transaction_reference',
        'invoice_documents',
        'should_be_invoiced',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'number',
        'tax_amount1',
        'tax_amount2',
        'tax_amount3',
        'uses_inclusive_taxes',
        'calculate_tax_by_amount',
        'purchase_order_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'e_invoice' => 'object',
    ];

    public static array $bulk_update_columns = [
        'tax1',
        'tax2',
        'tax3',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'should_be_invoiced',
        'uses_inclusive_taxes',
        'private_notes',
        'public_notes',
    ];

    protected $touches = [];

    public function toSearchableArray()
    {
        $locale = $this->company->locale();
        
        App::setLocale($locale);

        return [
            'id' => $this->id,
            'name' => ctrans('texts.expense') . " " . $this->number . ' | ' . Number::formatMoney($this->amount, $this->company) . ' | ' . $this->translateDate($this->date, $this->company->date_format(), $locale),
            'hashed_id' => $this->hashed_id,
            'number' => $this->number,
            'is_deleted' => $this->is_deleted,
            'amount' => (float) $this->amount,
            'date' => $this->date,
            'custom_value1' => (string)$this->custom_value1,
            'custom_value2' => (string)$this->custom_value2,
            'custom_value3' => (string)$this->custom_value3,
            'custom_value4' => (string)$this->custom_value4,
            'company_key' => $this->company->company_key,
        ];
    }

    public function getScoutKey()
    {
        return $this->hashed_id;
    }
    
    public function getEntityType()
    {
        return self::class;
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigned_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function purchase_order()
    {
        return $this->hasOne(PurchaseOrder::class)->withTrashed();
    }

    public function translate_entity()
    {
        return ctrans('texts.expense');
    }

    public function currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function invoice_currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class)->withTrashed();
    }

    public function payment_type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BankTransaction::class)->withTrashed();
    }

    public function stringStatus()
    {
        if ($this->is_deleted) {
            return ctrans('texts.deleted');
        } elseif ($this->payment_date) {
            return ctrans('texts.paid');
        } elseif ($this->invoice_id) {
            return ctrans('texts.invoiced');
        } elseif ($this->should_be_invoiced) {
            return ctrans('texts.pending');
        } elseif ($this->trashed()) {
            return ctrans('texts.archived');
        }

        return ctrans('texts.logged');
    }

    public function calculatedTaxRate($tax_amount, $tax_rate): float
    {

        if ($this->calculate_tax_by_amount) {
            if ($this->uses_inclusive_taxes) {
                return round((($tax_amount / $this->amount) * 100 * 1000) / 10) / 100;
            }

            return round((($tax_amount / $this->amount) * 1000) / 10) / 1;
        }

        return $tax_rate;

    }
}
