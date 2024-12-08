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

namespace App\DataMapper\Tax;

use App\DataMapper\Tax\ZipTax\Response;

/**
 * InvoiceTaxData
 *
 * Definition for the invoice tax data structure
 */
class TaxData
{
    public int $updated_at;

    public function __construct(public Response $origin)
    {
        // @phpstan-ignore-next-line
        foreach ($origin as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
