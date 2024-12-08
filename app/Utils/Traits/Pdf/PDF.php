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

namespace App\Utils\Traits\Pdf;

use setasign\Fpdi\Fpdi;

class PDF extends FPDI
{
    public $text_alignment = 'L';

    public function Footer()
    {
        $this->SetXY(config('ninja.pdf_page_numbering_x_alignment'), config('ninja.pdf_page_numbering_y_alignment'));

        $this->SetFont('Arial', 'I', 9);

        $this->SetTextColor(135, 135, 135);

        $trans = ctrans('texts.pdf_page_info', ['current' => $this->PageNo(), 'total' => '{nb}']);

        try {
            $trans = mb_convert_encoding($trans, 'ISO-8859-1', 'UTF-8');
        } catch (\Exception $e) {
        }

        $this->Cell(0, 5, $trans, 0, 0, $this->text_alignment);
    }

    public function setAlignment($alignment)
    {
        if (in_array($alignment, ['C', 'L', 'R'])) {
            $this->text_alignment = $alignment;
        }

        return $this;
    }
}
