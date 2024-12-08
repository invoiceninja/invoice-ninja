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

namespace App\Exceptions;

use Exception;

class PeppolValidationException extends Exception
{
    protected string $field = '';

    public function __construct($message, $field, $code = 0, Exception $previous = null)
    {
        // Store the custom data
        $this->field = $field;

        // Ensure that everything is assigned properly by calling the parent constructor
        parent::__construct($message, $code, $previous);
    }

    public function getInvalidField()
    {
        return $this->field;
    }
}
