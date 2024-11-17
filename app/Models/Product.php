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

use App\DataMapper\ProductSync;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;
use League\CommonMark\CommonMarkConverter;

/**
 * App\Models\Product
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $product_key
 * @property string|null $notes
 * @property string|null $hash
 * @property float $cost
 * @property float $price
 * @property float $quantity
 * @property string|null $tax_name1
 * @property float $tax_rate1
 * @property string|null $tax_name2
 * @property float $tax_rate2
 * @property string|null $tax_name3
 * @property float $tax_rate3
 * @property int|null $deleted_at
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property object|null $sync
 * @property bool $is_deleted
 * @property float $in_stock_quantity
 * @property bool $stock_notification
 * @property int $stock_notification_threshold
 * @property int|null $max_quantity
 * @property string|null $product_image
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Company $company
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @property int|null $tax_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereTaxId($value)
 * @mixin \Eloquent
 */
class Product extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;

    public const PRODUCT_TYPE_PHYSICAL = 1;
    public const PRODUCT_TYPE_SERVICE = 2;
    public const PRODUCT_TYPE_DIGITAL = 3;
    public const PRODUCT_TYPE_SHIPPING = 4;
    public const PRODUCT_TYPE_EXEMPT = 5;
    public const PRODUCT_TYPE_REDUCED_TAX = 6;
    public const PRODUCT_TYPE_OVERRIDE_TAX = 7;
    public const PRODUCT_TYPE_ZERO_RATED = 8;
    public const PRODUCT_TYPE_REVERSE_TAX = 9;
    public const PRODUCT_INTRA_COMMUNITY = 10;

    protected $fillable = [
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'product_key',
        'notes',
        'cost',
        'price',
        'quantity',
        'tax_name1',
        'tax_name2',
        'tax_name3',
        'tax_rate1',
        'tax_rate2',
        'tax_rate3',
        'in_stock_quantity',
        'stock_notification_threshold',
        'stock_notification',
        'max_quantity',
        'product_image',
        'tax_id',
    ];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'sync' => ProductSync::class,
    ];

    public array $ubl_tax_map = [
        self::PRODUCT_TYPE_REVERSE_TAX => 'AE', // VAT_REVERSE_CHARGE =
        self::PRODUCT_TYPE_EXEMPT => 'E', // EXEMPT_FROM_TAX =
        self::PRODUCT_TYPE_PHYSICAL => 'S', // STANDARD_RATE =
        self::PRODUCT_TYPE_ZERO_RATED => 'Z', // ZERO_RATED_GOODS =
        //  self::PRODUCT_TYPE_ZERO_RATED => 'G', // FREE_EXPORT_ITEM =
        //  self::PRODUCT_TYPE_ZERO_RATED => 'O', // OUTSIDE_TAX_SCOPE =
        //  self::PRODUCT_TYPE_EXEMPT => 'K', // EEA_GOODS_AND_SERVICES =
        //  self::PRODUCT_TYPE_PHYSICAL => 'L', // CANARY_ISLANDS_INDIRECT_TAX =
        //  self::PRODUCT_TYPE_PHYSICAL => 'M', // CEUTA_AND_MELILLA =
        //  self::PRODUCT_TYPE_PHYSICAL => 'B', // TRANSFERRED_VAT_ITALY =
        //  self::PRODUCT_TYPE_PHYSICAL => 'A', // MIXED_TAX_RATE =
        self::PRODUCT_TYPE_REDUCED_TAX => 'AA', // LOWER_RATE =
        //  self::PRODUCT_TYPE_PHYSICAL => 'AB', // EXEMPT_FOR_RESALE =
        //  self::PRODUCT_TYPE_PHYSICAL => 'AC', // VAT_NOT_NOW_DUE =
        //  self::PRODUCT_TYPE_PHYSICAL => 'AD', // VAT_DUE_PREVIOUS_INVOICE =
        //  self::PRODUCT_TYPE_PHYSICAL => 'B', // TRANSFERRED_VAT =
        //  self::PRODUCT_TYPE_PHYSICAL => 'C', // DUTY_PAID_BY_SUPPLIER =
        //  self::PRODUCT_TYPE_PHYSICAL => 'D', // VAT_MARGIN_SCHEME_TRAVEL_AGENTS =
        //  self::PRODUCT_TYPE_PHYSICAL => 'F', // VAT_MARGIN_SCHEME_SECOND_HAND_GOODS =
        //  self::PRODUCT_TYPE_PHYSICAL => 'H', // HIGHER_RATE =
        //  self::PRODUCT_TYPE_PHYSICAL => 'I', // VAT_MARGIN_SCHEME_WORKS_OF_ART =
        //  self::PRODUCT_TYPE_PHYSICAL => 'J', // VAT_MARGIN_SCHEME_COLLECTORS_ITEMS =
        //  self::PRODUCT_TYPE_PHYSICAL => 'K', // VAT_EXEMPT_EEA_INTRA_COMMUNITY =
        //  self::PRODUCT_TYPE_PHYSICAL => 'L', // CANARY_ISLANDS_TAX =
        //  self::PRODUCT_TYPE_PHYSICAL => 'M', // TAX_CEUTA_MELILLA =
        //  self::PRODUCT_TYPE_PHYSICAL => 'O', // SERVICES_OUTSIDE_SCOPE =
    ];

    public array $ubl_tax_translations = [
        'texts.reverse_tax' => 'AE', // VAT_REVERSE_CHARGE
        'texts.tax_exempt' => 'E', // EXEMPT_FROM_TAX
        'texts.physical_goods' => 'S', // STANDARD_RATE
        'texts.zero_rated' => 'Z', // ZERO_RATED_GOODS
        'ubl.vat_exempt_eea_intra_community' => 'K', // VAT_EXEMPT_EEA_INTRA_COMMUNITY
        'ubl.free_export_item' => 'G', // FREE_EXPORT_ITEM
        'ubl.outside_tax_scope' => 'O', // OUTSIDE_TAX_SCOPE
        'ubl.eea_goods_and_services' => 'K', // EEA_GOODS_AND_SERVICES
        'ubl.canary_islands_indirect_tax' => 'L', // CANARY_ISLANDS_INDIRECT_TAX
        'ubl.ceuta_and_melilla' => 'M', // CEUTA_AND_MELILLA
        'ubl.transferred_vat_italy' => 'B', // TRANSFERRED_VAT_ITALY
        'ubl.mixed_tax_rate' => 'A', // MIXED_TAX_RATE
        'ubl.lower_rate' => 'AA', // LOWER_RATE
        'ubl.exempt_for_resale' => 'AB', // EXEMPT_FOR_RESALE
        'ubl.vat_not_now_due' => 'AC', // VAT_NOT_NOW_DUE
        'ubl.vat_due_previous_invoice' => 'AD', // VAT_DUE_PREVIOUS_INVOICE
        'ubl.transferred_vat' => 'B', // TRANSFERRED_VAT
        'ubl.duty_paid_by_supplier' => 'C', // DUTY_PAID_BY_SUPPLIER
        'ubl.vat_margin_scheme_travel_agents' => 'D', // VAT_MARGIN_SCHEME_TRAVEL_AGENTS
        'ubl.vat_margin_scheme_second_hand_goods' => 'F', // VAT_MARGIN_SCHEME_SECOND_HAND_GOODS
        'ubl.higher_rate' => 'H', // HIGHER_RATE
        'ubl.vat_margin_scheme_works_of_art' => 'I', // VAT_MARGIN_SCHEME_WORKS_OF_ART
        'ubl.vat_margin_scheme_collectors_items' => 'J', // VAT_MARGIN_SCHEME_COLLECTORS_ITEMS
        'ubl.canary_islands_tax' => 'L', // CANARY_ISLANDS_TAX
        'ubl.tax_ceuta_melilla' => 'M', // TAX_CEUTA_MELILLA
        'ubl.services_outside_scope' => 'O', // SERVICES_OUTSIDE_SCOPE
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function translate_entity()
    {
        return ctrans('texts.product');
    }

    public function markdownNotes()
    {
        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
            'renderer' => [
                'soft_break' => '<br>',
            ],
        ]);

        return $converter->convert($this->notes ?? '');
    }

    public static function markdownHelp(?string $notes = '')
    {

        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
            'renderer' => [
                'soft_break' => '<br>',
            ],
        ]);

        return $converter->convert($notes ?? '');

    }

    public function portalUrl($use_react_url): string
    {
        return $use_react_url ? config('ninja.react_url') . "/#/products/{$this->hashed_id}/edit" : config('ninja.app_url');
    }
}
