<?php

namespace App\Models;

use App\Services\Project\ProjectService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class Project.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int|null $client_id
 * @property string $name
 * @property float $task_rate
 * @property string|null $due_date
 * @property string|null $private_notes
 * @property float $budgeted_hours
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $public_notes
 * @property bool $is_deleted
 * @property string|null $number
 * @property string $color
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company $company
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read Project|null $project
 * @property-read int|null $tasks_count
 * @property-read \App\Models\User $user
 * @property-read \App\Models\User $assigned_user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\ProjectFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Project filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Project newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Project newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Project onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Project query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Project withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Project withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @mixin \Eloquent
 */
class Project extends BaseModel
{
    use SoftDeletes;
    use PresentableTrait;
    use Filterable;

    protected $fillable = [
        'name',
        'client_id',
        'task_rate',
        'private_notes',
        'public_notes',
        'due_date',
        'budgeted_hours',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'assigned_user_id',
        'color',
        'number',
    ];

    protected $with = [
        'documents',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    protected $touches = [];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class)->withTrashed();
    }

    public function documents()
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

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->withTrashed();
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

     /**
     * Service entry points.
     *
     * @return ProjectService
     */
    public function service(): ProjectService
    {
        return new ProjectService($this);
    }

    public function translate_entity()
    {
        return ctrans('texts.project');
    }
}
