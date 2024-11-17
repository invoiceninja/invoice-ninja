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

use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\ClientGatewayToken
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $client_id
 * @property string|null $token
 * @property string|null $routing_number
 * @property int $company_gateway_id
 * @property string|null $gateway_customer_reference
 * @property int $gateway_type_id
 * @property bool $is_default
 * @property object|null $meta
 * @property int|null $deleted_at
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property bool $is_deleted
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\CompanyGateway|null $gateway
 * @property-read \App\Models\GatewayType|null $gateway_type
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @mixin \Eloquent
 */
class ClientGatewayToken extends BaseModel
{
    use MakesDates;
    use SoftDeletes;

    protected $casts = [
        'meta' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $appends = [
        'hashed_id',
    ];

    protected $fillable = [
        'token',
        'routing_number',
        'gateway_customer_reference',
        'gateway_type_id',
        'meta',
        'client_id',
        'is_default',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function gateway(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CompanyGateway::class, 'id', 'company_gateway_id');
    }

    public function gateway_type(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(GatewayType::class, 'id', 'gateway_type_id');
    }

    /**
     * Company
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
