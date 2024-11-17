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

namespace App\Livewire\Flow2;

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Utils\Traits\WithSecureContext;

class Terms extends Component
{
    use WithSecureContext;

    public $variables;

    public function mount()
    {
        $this->variables = $this->getContext()['variables'];
    }

    #[Computed()]
    public function invoice()
    {
        return $this->getContext()['invoices']->first();
    }

    public function render()
    {
        return render('components.livewire.terms');
    }
}
