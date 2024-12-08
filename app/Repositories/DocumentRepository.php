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

namespace App\Repositories;

use App\Models\Document;

/**
 * Class for document repository.
 */
class DocumentRepository extends BaseRepository
{
    public function delete($document)
    {
        $document->deleteFile();
        $document->forceDelete();

        if ($document->documentable) {
            $document->documentable->touch();
        }
    }

    public function restore($document)
    {
        // if (! $document->trashed()) {
        //     return;
        // }

        // $document->restore();
    }

    public function archive($document)
    {
    }
}
