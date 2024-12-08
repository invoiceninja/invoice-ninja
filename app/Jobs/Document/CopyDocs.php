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

namespace App\Jobs\Document;

use App\Models\User;
use App\Models\Company;
use App\Models\Document;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CopyDocs implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    /**
     *
     */
    public function __construct(private \Illuminate\Support\Collection $document_ids, private $entity, private string $db)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDb($this->db);

        Document::query()
                ->whereIn('id', $this->document_ids)
                ->where('company_id', $this->entity->company_id)
                ->each(function ($document) {

                    /** @var \App\Models\Document $document */
                    $file = $document->getFile();

                    $extension = pathinfo($document->name, PATHINFO_EXTENSION);

                    $new_hash = \Illuminate\Support\Str::random(32) . "." . $extension;

                    $relative_path = "{$this->entity->company->company_key}/documents/{$new_hash}";

                    Storage::disk($document->disk)->put(
                        $relative_path,
                        $file,
                    );

                    // $instance = Storage::disk($document->disk)->path("{$this->entity->company->company_key}/documents/{$new_hash}");

                    $new_doc = new Document();
                    $new_doc->user_id = $this->entity->user_id;
                    $new_doc->company_id = $this->entity->company_id;
                    $new_doc->url = $relative_path;
                    $new_doc->name = $document->name;
                    $new_doc->type = $extension;
                    $new_doc->disk = $document->disk;
                    $new_doc->hash = $new_hash;
                    $new_doc->size = $document->size;
                    $new_doc->width = $document->width;
                    $new_doc->height = $document->height;
                    $new_doc->is_public = $document->is_public;

                    $this->entity->documents()->save($new_doc);

                });
    }

}