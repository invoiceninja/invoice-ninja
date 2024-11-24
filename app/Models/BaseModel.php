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

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use App\Jobs\Entity\CreateRawPdf;
use App\Jobs\Util\WebhookHandler;
use App\Models\Traits\Excludable;
use App\Services\PdfMaker\PdfMerge;
use Illuminate\Database\Eloquent\Model;
use App\Utils\Traits\UserSessionAttributes;
use App\Services\EDocument\Jobes\SendEDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;

/**
 * Class BaseModel
 *
 * @method scope() static
 * @method company() static
 * @package App\Models
 * @property-read mixed $hashed_id
 * @property string $number
 * @property object|array|null $e_invoice
 * @property int $company_id
 * @property int $id
 * @property int $user_id
 * @property int $assigned_user_id
 * @method BaseModel service()
 * @property \App\Models\Company $company
 * @method static BaseModel find($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel<static> company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel|Illuminate\Database\Eloquent\Relations\BelongsTo|\Awobaz\Compoships\Database\Eloquent\Relations\BelongsTo|\App\Models\Company company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel|Illuminate\Database\Eloquent\Relations\HasMany|BaseModel orderBy()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel on(?string $connection = null)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel with($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel newModelQuery($query)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel newQuery($query)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel whereId($query)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel whereIn($query)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel where($query)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel count()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel create($query)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel insert($query)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel orderBy($column, $direction)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel invitations()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel whereHas($query)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel without($query)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceInvitation | \App\Models\CreditInvitation | \App\Models\QuoteInvitation | \App\Models\RecurringInvoiceInvitation> $invitations
 * @property-read int|null $invitations_count
 * @method int companyId()
 * @method createInvitations()
 * @method Builder scopeCompany(Builder $builder)
 * @method static \Illuminate\Database\Eloquent\Builder<static> company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel|\Illuminate\Database\Query\Builder withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel|\Illuminate\Database\Query\Builder onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel|\Illuminate\Database\Query\Builder withoutTrashed()
 * @mixin \Eloquent
 * @mixin \Illuminate\Database\Eloquent\Builder
 *
 * @property \Illuminate\Support\Collection $tax_map
 * @property array $total_tax_map
 */
class BaseModel extends Model
{
    use MakesHash;
    use UserSessionAttributes;
    use HasFactory;
    use Excludable;
    use MakesDates;

    public int $max_attachment_size = 3000000;

    protected $appends = [
        'hashed_id',
    ];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function getHashedIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function dateMutator($value)
    {
        return (new Carbon($value))->format('Y-m-d');
    }

    // public function __call($method, $params)
    // {
    //     $entity = strtolower(class_basename($this));

    //     if ($entity) {
    //         $configPath = "modules.relations.$entity.$method";

    //         if (config()->has($configPath)) {
    //             $function = config()->get($configPath);

    //             return call_user_func_array([$this, $function[0]], $function[1]);
    //         }
    //     }

    //     return parent::__call($method, $params);
    // }

    /**
    * @param  \Illuminate\Database\Eloquent\Builder  $query
    * @return \Illuminate\Database\Eloquent\Builder
    */
    public function scopeCompany($query): \Illuminate\Database\Eloquent\Builder
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $query->where("{$query->getQuery()->from}.company_id", $user->companyId());

        return $query;
    }

    /**
     * @deprecated version
     */
    public function scopeScope($query)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $query->where($this->getTable().'.company_id', '=', $user->company()->id);

        return $query;
    }

    /**
     * Gets the settings by key.
     *
     * When we need to update a setting value, we need to harvest
     * the object of the setting. This is not possible when using the merged settings
     * as we do not know which object the setting has come from.
     *
     * The following method will return the entire object of the property searched for
     * where a value exists for $key.
     *
     * This object can then be mutated by the handling class,
     * to persist the new settings we will also need to pass back a
     * reference to the parent class.
     *
     * @param $key The key of property
     * @deprecated
     */
    // public function getSettingsByKey($key)
    // {
    //     /* Does Setting Exist @ client level */
    //     if (isset($this->getSettings()->{$key})) {
    //         return $this->getSettings()->{$key};
    //     } else {
    //         return (new CompanySettings($this->company->settings))->{$key};
    //     }
    // }

    // public function setSettingsByEntity($entity, $settings)
    // {
    //     switch ($entity) {
    //         case Client::class:

    //             $this->settings = $settings;
    //             $this->save();
    //             $this->fresh();
    //             break;
    //         case Company::class:

    //             $this->company->settings = $settings;
    //             $this->company->save();
    //             break;
    //             //todo check that saving any other entity (Invoice:: RecurringInvoice::) settings is valid using the default:
    //         default:
    //             $this->client->settings = $settings;
    //             $this->client->save();
    //             break;
    //     }
    // }

    /**
     * Gets the settings.
     *
     * Generic getter for client settings
     *
     * @return ClientSettings.
     */
    // public function getSettings()
    // {
    //     return new ClientSettings($this->settings);
    // }

    /**
     * Retrieve the model for a bound value.
     *
     * @param mixed $value
     * @param mixed $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            throw new ModelNotFoundException("Record with value {$value} not found");
        }

        return $this
            ->withTrashed()
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }

    /**
     * @param string $extension
     * @return string
     */
    public function getFileName($extension = 'pdf')
    {
        return $this->numberFormatter().'.'.$extension;
    }

    public function getDeliveryNoteName($extension = 'pdf')
    {

        $number =  ctrans("texts.delivery_note"). "_" . $this->numberFormatter().'.'.$extension;

        $formatted_number =  mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $number);

        $formatted_number = mb_ereg_replace("([\.]{2,})", '', $formatted_number);

        $formatted_number = preg_replace('/\s+/', '_', $formatted_number);

        return \Illuminate\Support\Str::ascii($formatted_number);

    }

    /**
    * @param string $extension
    * @return string
    */
    public function getEFileName($extension = 'pdf')
    {
        return ctrans("texts.e_invoice"). "_" . $this->numberFormatter().'.'.$extension;
    }

    // public function numberFormatter()
    // {
    //     $number = strlen($this->number) >= 1 ? $this->translate_entity() . "_" . $this->number : class_basename($this) . "_" . Str::random(5);

    //     // Remove control characters
    //     $formatted_number = preg_replace('/[\x00-\x1F\x7F]/u', '', $number);

    //     // Replace slash, backslash, and null byte with underscore
    //     $formatted_number = str_replace(['/', '\\', "\0"], '_', $formatted_number);

    //     // Remove any other characters that are invalid in most filesystems
    //     $formatted_number = str_replace(['<', '>', ':', '"', '|', '?', '*'], '', $formatted_number);

    //     // Replace multiple spaces or underscores with a single underscore
    //     $formatted_number = preg_replace('/[\s_]+/', '_', $formatted_number);

    //     // Trim underscores from start and end
    //     $formatted_number = trim($formatted_number, '_');

    //     // Ensure the filename is not empty
    //     if (empty($formatted_number)) {
    //         $formatted_number = 'file_' . Str::random(5);
    //     }

    //     // Limit the length of the filename (adjust as needed)
    //     $formatted_number = mb_substr($formatted_number, 0, 255);

    //     return $formatted_number;
    // }

    public function numberFormatter()
    {
        $number = strlen($this->number) >= 1 ? $this->translate_entity() . "_" . $this->number : class_basename($this) . "_" . Str::random(5);

        $formatted_number =  mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $number);

        $formatted_number = mb_ereg_replace("([\.]{2,})", '', $formatted_number);

        $formatted_number = preg_replace('/\s+/', '_', $formatted_number);

        return \Illuminate\Support\Str::ascii($formatted_number);
    }

    public function translate_entity()
    {
        return ctrans('texts.item');
    }

    /**
     * Model helper to send events for webhooks
     *
     * @param  int    $event_id
     * @param  string $additional_data optional includes
     *
     * @return void
     */
    public function sendEvent(int $event_id, string $additional_data = ""): void
    {
        $subscriptions = Webhook::where('company_id', $this->company_id)
                                 ->where('event_id', $event_id)
                                 ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event_id, $this->withoutRelations(), $this->company, $additional_data);
        }

        // special catch here for einvoicing eventing
        if ($event_id == Webhook::EVENT_SENT_INVOICE && ($this instanceof Invoice) && is_null($this->backup) && $this->client->peppolSendingEnabled()) {
            \App\Services\EDocument\Jobs\SendEDocument::dispatch(get_class($this), $this->id, $this->company->db);
        }

    }


    /**
     * arrayFilterRecursive nee filterNullsRecursive
     *
     * Removes null properties from an array
     *
     * @param  array $array
     * @return array
     */
    public function filterNullsRecursive(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                // Recursively filter the nested array
                $array[$key] = $this->filterNullsRecursive($value);
            }
            // Remove null values
            if (is_null($array[$key])) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * Returns the base64 encoded PDF string of the entity
     * @deprecated - unused implementation
     */
    public function fullscreenPdfViewer($invitation = null): string
    {

        if (! $invitation) {
            if ($this->invitations()->exists()) {
                $invitation = $this->invitations()->first();
            } else {
                $this->service()->createInvitations();
                $invitation = $this->invitations()->first();
            }
        }

        if (! $invitation) {
            throw new \Exception('Hard fail, could not create an invitation.');
        }

        return "data:application/pdf;base64,".base64_encode((new CreateRawPdf($invitation))->handle());

    }

    /**
     * Takes a entity prop as first argument
     * along with an array of variables and performs
     * a string replace on the prop.
     *
     * @param string $field
     * @param array $variables
     * @return string
     */
    public function parseHtmlVariables(string $field, array $variables): string
    {
        if (!$this->{$field}) {
            return '';
        }

        $section = strtr($this->{$field}, $variables['labels']);

        return strtr($section, $variables['values']);

    }

    /**
     * Merged PDFs associated with the entity / company
     * into a single document
     *
     * @param  string $pdf
     * @todo need to provide a fallback here in case the headers of the PDF do not allow merging
     * @return mixed
     */
    public function documentMerge(string $pdf): mixed
    {
        $files = collect([$pdf]);

        $entity_docs = $this->documents()
        ->where('is_public', true)
        ->get()
        ->filter(function ($document) {
            return $document->size < $this->max_attachment_size && stripos($document->name, ".pdf") !== false;
        })->map(function ($d) {
            return $d->getFile();
        });

        $files->push($entity_docs);

        $company_docs = $this->company->documents()
        ->where('is_public', true)
        ->get()
        ->filter(function ($document) {
            return $document->size < $this->max_attachment_size && stripos($document->name, ".pdf") !== false;
        })->map(function ($d) {
            return $d->getFile();
        });

        $files->push($company_docs);

        try {
            $pdf = (new PdfMerge($files->flatten()->toArray()))->run();
            return $pdf;

        } catch (\Exception $e) {
            nlog("Exception:: BaseModel:: PdfMerge::" . $e->getMessage());
        }

        return $pdf;
    }
}
