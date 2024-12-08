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

namespace App\Http\Controllers;

use App\Http\Requests\Import\ImportRequest;
use App\Http\Requests\Import\PreImportRequest;
use App\Jobs\Import\CSVIngest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\Csv\Reader;
use League\Csv\Statement;

class ImportController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param PreImportRequest $request
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     *
     * @OA\Post(
     *      path="/api/v1/preimport",
     *      operationId="preimport",
     *      tags={"imports"},
     *      summary="Pre Import checks - returns a reference to the job and the headers of the CSV",
     *      description="Pre Import checks - returns a reference to the job and the headers of the CSV",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\RequestBody(
     *         description="The CSV file",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             )
     *         )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a reference to the file",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function preimport(PreImportRequest $request)
    {
        // Create a reference
        $hash = Str::random(32);

        $data = [
            'hash'     => $hash,
            'mappings' => [],
        ];
        /** @var UploadedFile $file */
        foreach ($request->files->get('files') as $entityType => $file) {
            $contents = file_get_contents($file->getPathname());

            $contents = $this->convertEncoding($contents);

            // Store the csv in cache with an expiry of 10 minutes
            Cache::put($hash.'-'.$entityType, base64_encode($contents), 1200);

            // Parse CSV
            $csv_array = $this->getCsvData($contents);

            $class_map = $this->getEntityMap($entityType);

            $hints = $this->setImportHints($entityType, $class_map::importable(), $csv_array[0]);

            $data['mappings'][$entityType] = [
                'available' => $class_map::importable(),
                'headers'   => array_slice($csv_array, 0, 2),
                'hints' => $hints,
            ];
        }

        return response()->json($data);
    }

    private function setImportHints($entity_type, $available_keys, $headers): array
    {
        $hints = [];

        $translated_keys = collect($available_keys)->map(function ($value, $key) {

            $parts = explode(".", $value);
            $index = $parts[0];
            $label = $parts[1] ?? $parts[0];

            return ['key' => $key, 'index' => ctrans("texts.{$index}"), 'label' => ctrans("texts.{$label}")];

        })->toArray();

        //Exact string match
        foreach ($headers as $key => $value) {

            foreach ($translated_keys as $tkey => $tvalue) {

                $concat_needle = str_ireplace(" ", "", $tvalue['index'].$tvalue['label']);
                $concat_value = str_ireplace(" ", "", $value);

                if ($this->testMatch($concat_value, $concat_needle)) {

                    $hit = $tvalue['key'];
                    $hints[$key] = $hit;
                    unset($translated_keys[$tkey]);
                    break;

                } else {
                    $hints[$key] = null;
                }

            }

        }

        //Label Match
        foreach ($headers as $key => $value) {

            if (isset($hints[$key])) {
                continue;
            }

            foreach ($translated_keys as $tkey => $tvalue) {

                if ($this->testMatch($value, $tvalue['label'])) {
                    $hit = $tvalue['key'];
                    $hints[$key] = $hit;
                    unset($translated_keys[$tkey]);
                    break;
                } else {
                    $hints[$key] = null;
                }

            }

        }

        //Index matching pass using the index of the translation here
        foreach ($headers as $key => $value) {
            if (isset($hints[$key])) {
                continue;
            }

            foreach ($translated_keys as $tkey => $tvalue) {
                if ($this->testMatch($value, $tvalue['index'])) {
                    $hit = $tvalue['key'];
                    $hints[$key] = $hit;
                    unset($translated_keys[$tkey]);
                    break;
                } else {
                    $hints[$key] = null;
                }
            }

        }

        return $hints;
    }

    private function testMatch($haystack, $needle): bool
    {
        return stripos($haystack, $needle) !== false;
    }

    private function convertEncoding($data)
    {

        // $enc = mb_detect_encoding($data, mb_list_encodings(), true);

        // if($enc !== false) {
        //     $data = mb_convert_encoding($data, "UTF-8", $enc);
        // }

        return $data;
    }

    public function import(ImportRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $data = $request->all();

        if (empty($data['hash'])) {
            // Create a reference
            $data['hash'] = $hash = Str::random(32);

            /** @var UploadedFile $file */
            foreach ($request->files->get('files') as $entityType => $file) {
                $contents = file_get_contents($file->getPathname());
                // Store the csv in cache with an expiry of 10 minutes
                Cache::put($hash.'-'.$entityType, base64_encode($contents), 600);
                nlog($hash.'-'.$entityType);
            }
        }

        unset($data['files']);
        CSVIngest::dispatch($data, $user->company());

        return response()->json(['message' => ctrans('texts.import_started')], 200);
    }

    private function getEntityMap($entity_type)
    {
        return sprintf('App\\Import\\Definitions\%sMap', ucfirst(Str::camel($entity_type)));
    }

    private function getCsvData($csvfile)
    {
        if (! ini_get('auto_detect_line_endings')) {
            ini_set('auto_detect_line_endings', '1');
        }

        $csv = Reader::createFromString($csvfile);
        $csvdelimiter = self::detectDelimiter($csvfile);
        $csv->setDelimiter($csvdelimiter);
        $stmt = new Statement();
        $data = iterator_to_array($stmt->process($csv));

        if (count($data) > 0) {
            $headers = $data[0];

            // Remove Invoice Ninja headers
            if (count($headers) && count($data) > 4) {
                $firstCell = $headers[0];

                if (strstr($firstCell, (string) config('ninja.app_name'))) {
                    array_shift($data); // Invoice Ninja...
                    array_shift($data); // <blank line>
                    array_shift($data); // Entity Type Header
                }
            }
        }

        return $this->convertData($data);
    }



    private function convertData(array $data): array
    {

        // List of encodings to check against
        $encodings = [
            'UTF-8',
            'ISO-8859-1',  // Latin-1
            'ISO-8859-2',  // Latin-2
            'WINDOWS-1252', // CP1252
            'SHIFT-JIS',
            'EUC-JP',
            'GB2312',
            'GBK',
            'BIG5',
            'ISO-2022-JP',
            'KOI8-R',
            'KOI8-U',
            'WINDOWS-1251', // CP1251
            'UTF-16',
            'UTF-32',
            'ASCII',
            'WINDOWS-1254', // Turkish, which sometimes includes Georgian
            'WINDOWS-1256', // Arabic, which sometimes includes Georgian
            'ISO-8859-10',
        ];

        foreach ($data as $key => $value) {
            // Only process strings
            if (is_string($value)) {
                // Detect the encoding of the string
                $detectedEncoding = mb_detect_encoding($value, $encodings, true);

                // If encoding is detected and it's not UTF-8, convert it to UTF-8
                if ($detectedEncoding && $detectedEncoding !== 'UTF-8') {
                    $array[$key] = mb_convert_encoding($value, 'UTF-8', $detectedEncoding);
                }
            }
        }

        return $data;
    }


    /**
     * Returns the best delimiter
     *
     * @param string $csvfile
     * @return string
     */
    public function detectDelimiter($csvfile): string
    {

        $delimiters = [',', '.', ';', '|'];
        $bestDelimiter = ',';
        $count = 0;

        // 10-01-2024 - A better way to resolve the csv file delimiter.
        $csvfile = substr($csvfile, 0, strpos($csvfile, "\n"));

        foreach ($delimiters as $delimiter) {

            if (substr_count(strstr($csvfile, "\n", true), $delimiter) >= $count) {
                $count = substr_count($csvfile, $delimiter);
                $bestDelimiter = $delimiter;
            }

        }

        /** @phpstan-ignore-next-line **/
        return $bestDelimiter ?? ',';

    }
}
