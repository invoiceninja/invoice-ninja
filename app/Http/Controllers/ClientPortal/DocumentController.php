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

namespace App\Http\Controllers\ClientPortal;

use App\Utils\TempFile;
use App\Models\Document;
use Illuminate\View\View;
use App\Libraries\MultiDB;
use App\Utils\Traits\MakesHash;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Document\DownloadMultipleDocumentsRequest;
use App\Http\Requests\ClientPortal\Documents\ShowDocumentRequest;

class DocumentController extends Controller
{
    use MakesHash;

    /**
     * @return Factory|View
     */
    public function index()
    {
        return render('documents.index');
    }

    /**
     * @param ShowDocumentRequest $request
     * @param Document $document
     * @return Factory|View
     */
    public function show(ShowDocumentRequest $request, Document $document)
    {
        return render('documents.show', [
            'document' => $document,
        ]);
    }

    public function download(ShowDocumentRequest $request, Document $document)
    {
        return Storage::disk($document->disk)->download($document->url, $document->name);
    }

    public function publicDownload(string $document_hash)
    {
        MultiDB::documentFindAndSetDb($document_hash);

        /** @var \App\Models\Document $document **/
        $document = Document::where('hash', $document_hash)->firstOrFail();

        $headers = ['Cache-Control:' => 'no-cache'];

        if (request()->input('inline') == 'true') {
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);
        }

        return Storage::disk($document->disk)->download($document->url, $document->name, $headers);
    }

    public function hashDownload(string $hash)
    {

        $hash = Cache::pull($hash);

        if (!$hash) {
            abort(404);
        }

        MultiDB::setDb($hash['db']);

        /** @var \App\Models\Document $document **/
        $document = Document::where('hash', $hash['doc_hash'])->firstOrFail();

        $headers = ['Cache-Control:' => 'no-cache'];

        if (request()->input('inline') == 'true') {
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);
        }

        return Storage::disk($document->disk)->download($document->url, $document->name, $headers);
    }


    public function downloadMultiple(DownloadMultipleDocumentsRequest $request)
    {
        /** @var \Illuminate\Database\Eloquent\Collection<Document> $documents **/
        $documents = Document::query()->whereIn('id', $this->transformKeys($request->file_hash))
            ->where('company_id', auth()->guard('contact')->user()->company_id)
            ->get();

        $zipFile = new \PhpZip\ZipFile();

        try {
            foreach ($documents as $document) {
                $zipFile->addFile(TempFile::path($document->filePath()), $document->name);
            }

            $filename = now().'-documents.zip';
            $filepath = sys_get_temp_dir().'/'.$filename;

            $zipFile->saveAsFile($filepath) // save the archive to a file
                   ->close(); // close archive

            return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        } catch (\PhpZip\Exception\ZipException $e) {
            // handle exception
        } finally {
            $zipFile->close();
        }
    }
}
