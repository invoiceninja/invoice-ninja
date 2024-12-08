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

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * App\Models\Document
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property string|null $url
 * @property string|null $preview
 * @property string|null $name
 * @property string|null $type
 * @property string|null $disk
 * @property string|null $hash
 * @property int|null $size
 * @property int|null $width
 * @property int|null $height
 * @property int $is_default
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property int|null $deleted_at
 * @property int $documentable_id
 * @property string $documentable_type
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int $is_public
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $documentable
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\DocumentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Document filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Document newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Document newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Document onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Document query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Document withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Document withoutTrashed()
 * @mixin \Eloquent
 */
class Document extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    public const DOCUMENT_PREVIEW_SIZE = 300; // pixels

    /**
     * @var array<string>
     */
    protected $fillable = [
        'is_default',
        'is_public',
        'name',
    ];

    /**
     * @var array<string>
     */
    protected $casts = [
        'is_public' => 'bool',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $touches = [
        // 'documentable'
    ];

    /**
     * @var array
     */
    public static $types = [
        'png' => [
            'mime' => 'image/png',
        ],
        'ai' => [
            'mime' => 'application/postscript',
        ],
        'jpeg' => [
            'mime' => 'image/jpeg',
        ],
        'jpg' => [
            'mime' => 'image/jpeg',
        ],
        'tiff' => [
            'mime' => 'image/tiff',
        ],
        'pdf' => [
            'mime' => 'application/pdf',
        ],
        'gif' => [
            'mime' => 'image/gif',
        ],
        'psd' => [
            'mime' => 'image/vnd.adobe.photoshop',
        ],
        'txt' => [
            'mime' => 'text/plain',
        ],
        'doc' => [
            'mime' => 'application/msword',
        ],
        'xls' => [
            'mime' => 'application/vnd.ms-excel',
        ],
        'ppt' => [
            'mime' => 'application/vnd.ms-powerpoint',
        ],
        'xlsx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
        'docx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'pptx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
    ];

    /**
     * @var array
     */
    public static $extraExtensions = [
        'jpg' => 'jpeg',
        'tif' => 'tiff',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    public function documentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function generateUrl($absolute = false)
    {
        $url = Storage::disk($this->disk)->url($this->url);

        if ($url && $absolute) {
            return url($url);
        }

        if ($url) {
            return $url;
        }

        return null;
    }

    public function generateRoute($absolute = false)
    {
        try {
            return route('api.documents.show', ['document' => $this->hashed_id]).'/download';
        } catch (\Exception $e) {
            nlog("Exception:: Document::" . $e->getMessage());
            return '';
        }
    }

    public function deleteFile()
    {
        Storage::disk($this->disk)->delete($this->url);
    }

    public function filePath()
    {
        return Storage::disk($this->disk)->url($this->url);
    }

    public function diskPath(): string
    {
        return Storage::disk($this->disk)->path($this->url);
    }

    public function getFile()
    {
        return Storage::get($this->url);
    }

    public function translate_entity()
    {
        return ctrans('texts.document');
    }

    public function link()
    {
        $entity_id = $this->encodePrimaryKey($this->documentable_id);
        $link = '';

        match($this->documentable_type) {
            'App\Models\Vendor' => $link = "/vendors/{$entity_id}",
            'App\Models\Project' => $link = "/projects/{$entity_id}",
            'invoices' => $link = "/invoices/{$entity_id}/edit",
            'App\Models\Quote' => $link = "/quotes/{$entity_id}/edit",
            'App\Models\Credit' => $link = "/credits/{$entity_id}/edit",
            'App\Models\Expense' => $link = "/expenses/{$entity_id}/edit",
            'App\Models\Payment' => $link = "/payments/{$entity_id}/edit",
            'App\Models\Task' => $link = "/tasks/{$entity_id}/edit",
            'App\Models\Client' => $link = "/clients/{$entity_id}",
            'App\Models\RecurringExpense' => $link = "/recurring_expenses/{$entity_id}/edit",
            'App\Models\RecurringInvoice' => $link = "/recurring_invoices/{$entity_id}/edit",
            default => $link = '',
        };

        return $link;
    }

    public function compress(): mixed
    {

        $image = $this->getFile();
        $catch_image = $image;

        if (!extension_loaded('imagick')) {
            return $catch_image;
        }

        try {
            $file = base64_encode($image);

            $img = new \Imagick(); //@phpstan-ignore-line
            $img->readImageBlob($file);
            $img->setImageCompression(true); //@phpstan-ignore-line
            $img->setImageCompressionQuality(40);

            return $img->getImageBlob();

        } catch (\Exception $e) {
            nlog("Exception:: Document::" . $e->getMessage());
            nlog($e->getMessage());
            return $catch_image;
        }

    }

    /**
     * Returns boolean based on checks for image.
     *
     * @return bool
     */
    public function isImage(): bool
    {
        if (in_array($this->type, ['png', 'jpeg', 'jpg', 'tiff', 'gif'])) {
            return true;
        }

        return false;
    }

}
