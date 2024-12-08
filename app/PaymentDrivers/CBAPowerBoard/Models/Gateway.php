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

namespace App\PaymentDrivers\CBAPowerBoard\Models;

class Gateway
{
    public function __construct(
        public string $_id,
        public string $name,
        public string $type,
        public string $mode,
        public string $created_at,
        public string $updated_at,
        public bool $archived,
        public bool $default,
        public string $verification_status = ''
    ) {
        $this->_id = $_id;
        $this->name = $name;
        $this->type = $type;
        $this->mode = $mode;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->archived = $archived;
        $this->default = $default;
        $this->verification_status = $verification_status;
    }
}
