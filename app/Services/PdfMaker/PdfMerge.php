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

namespace App\Services\PdfMaker;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;

class PdfMerge
{
    /**
     * __construct
     *
     * @param  array $files
     * @return void
     */
    public function __construct(private array $files)
    {
    }

    public function run()
    {
        $pdf = new FPDI();

        foreach ($this->files as $file) {
            
            $pageCount = 0;

            try {
                // Try to open with FPDI first
                $pageCount = $pdf->setSourceFile(StreamReader::createByString($file));
            } catch (\setasign\Fpdi\PdfParser\PdfParserException $e) {
                // If FPDI fails, try downgrading the PDF

                if(class_exists(\Modules\Admin\Services\PdfParse::class)){
                    
                    $downgradedPdf = \Modules\Admin\Services\PdfParse::downgrade($file);
                    
                    $pageCount = $pdf->setSourceFile(StreamReader::createByString($downgradedPdf));
                }

            }

            for ($i = 0; $i < $pageCount; $i++) {
                $tpl = $pdf->importPage($i + 1, '/MediaBox');
                $pdf->addPage();
                $pdf->useTemplate($tpl);
            }
        }

        return $pdf->Output('S');
    }

}
