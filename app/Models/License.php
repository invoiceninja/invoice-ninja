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

use App\Casts\AsTaxEntityCollection;
use App\DataMapper\EInvoice\TaxEntity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

/**
 * App\Models\License
 *
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $license_key
 * @property int|null $is_claimed
 * @property string|null $transaction_reference
 * @property int|null $product_id
 * @property int|null $recurring_invoice_id
 * @property int|null $e_invoice_quota
 * @property bool $is_flagged
 * @property array|null $entities
 * @property-read \App\Models\RecurringInvoice $recurring_invoice
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EInvoiceToken> $e_invoicing_tokens
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|License newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|License newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|License onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|License query()
 * @method static \Illuminate\Database\Eloquent\Builder|License whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereIsClaimed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereLicenseKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereRecurringInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|License withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|License withoutTrashed()
 * @mixin \Eloquent
 */
class License extends StaticModel
{
    use SoftDeletes;

    protected $casts = [
        'created_at' => 'date',
        'entities' => AsTaxEntityCollection::class,
    ];

    /**
     * expiry
     *
     * @return string
     */
    public function expiry(): string
    {
        return $this->created_at->addYear()->format('Y-m-d');
    }

    /**
     * recurring_invoice
     *
     */
    public function recurring_invoice()
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    /**
     * e_invoicing_tokens
     *
     */
    public function e_invoicing_tokens()
    {
        return $this->hasMany(EInvoicingToken::class, 'license', 'license_key');
    }

    /**
     * addEntity
     *
     * @param  TaxEntity $entity
     * @return void
     */
    public function addEntity(TaxEntity $entity)
    {
        $entities = $this->entities;

        if (is_array($entities)) {
            $entities[] = $entity;
        } else {
            $entities = [$entity];
        }

        $this->entities = $entities;

        $this->save();

    }

    /**
     * removeEntity
     *
     * @param  TaxEntity $entity
     * @return void
     */
    public function removeEntity(TaxEntity $entity)
    {

        if (!is_array($this->entities)) {
            return;
        }

        $entities = array_filter($this->entities, function ($existingEntity) use ($entity) {
            return $existingEntity->legal_entity_id !== $entity->legal_entity_id;
        });

        $this->entities = $entities;

        $this->save();

    }
    
    /**
     * updateEntity
     *
     * @param  TaxEntity $entity
     * @return void
     */
    public function updateEntity(TaxEntity $entity, string $search_key = 'legal_entity_id')
    {
                
        if (!is_array($this->entities)) {
            return;
        }

        $entities = $this->entities;

        foreach ($entities as $key => $existingEntity) {
            if ($existingEntity->{$search_key} === $entity->{$search_key}) {
                $entities[$key] = $entity;
                break;
            }
        }

        $this->setAttribute('entities', $entities);
        $this->save();

    }

    public function countEntities(): int
    {
                
        if (!is_array($this->entities)) {
            return 0;
        }

        return count($this->entities);
    }

    public function findEntity(string $key, mixed $value): ?TaxEntity
    {
                
        if (!is_array($this->entities)) {
            return null;
        }

        foreach ($this->entities as $entity) {
            if (property_exists($entity, $key) && $entity->{$key} === $value) {
                return $entity;
            }
        }

        return null;

    }

    /**
     * isValid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->created_at->gte(now()->subYear());
    }
}
