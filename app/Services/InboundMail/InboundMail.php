<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\InboundMail;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * InboundMail.
 */
class InboundMail
{
    public string $to;

    public string $from;

    public ?string $subject = null;

    public ?string $body = null;

    public ?UploadedFile $body_document = null;

    public string $text_body;

    /** @var array $documents */
    public array $documents = [];

    public ?Carbon $date = null;

    public function __construct()
    {
    }
}
