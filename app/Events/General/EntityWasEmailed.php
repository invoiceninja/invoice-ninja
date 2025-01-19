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

namespace App\Events\General;

use App\Models\Company;
use Illuminate\Queue\SerializesModels;

/**
 * Class EntityWasEmailed.
 */
class EntityWasEmailed
{
    use SerializesModels;

    public $invitation;

    public $company;

    public $event_vars;

    public $template;

    public function __construct($invitation, Company $company, array $event_vars, string $template)
    {
        $this->invitation = $invitation;
        $this->company = $company;
        $this->event_vars = $event_vars;
        $this->template = $template;
    }
}
