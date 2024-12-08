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

use App\Models\License;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * App\Models\EInvoicingLog
 *
 * @package App\Models
 * @property int $id
 * @property string $tenant_id (sent|received)
 * @property string $direction
 * @property int $legal_entity_id
 * @property string|null $license_key The license key string
 * @property string|null $notes
 * @property int $counter
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property-read \App\Models\License $license
 * @mixin \Eloquent
 *
 */
class EInvoicingLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'direction',
        'legal_entity_id',
        'license_key',
        'notes',
        'counter',
    ];

    protected $casts = [
        'created_at' => 'date',
        'updated_at' => 'date',
        'deleted_at' => 'date',
    ];

    /**
     * license
     *
     */
    public function license(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(License::class, 'license_key', 'license_key');
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id', 'id');
    }

    

}

