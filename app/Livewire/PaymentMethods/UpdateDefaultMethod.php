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

namespace App\Livewire\PaymentMethods;

use Livewire\Component;
use App\Libraries\MultiDB;
use Livewire\Attributes\Computed;
use App\Models\ClientGatewayToken;

class UpdateDefaultMethod extends Component
{
    public $db;

    public $token_id;

    public function mount()
    {
        MultiDB::setDb($this->db);
    }

    #[Computed]
    public function token()
    {
        return ClientGatewayToken::withTrashed()->find($this->token_id);
    }

    public function makeDefault(): void
    {
        
        MultiDB::setDb($this->db);
        

        if ($this->token()->is_default) {
            return;
        }

        $this->token()->client->gateway_tokens()->update(['is_default' => 0]);

        $token = $this->token();
        $token->is_default = 1;
        $token->save();

        $this->dispatch('UpdateDefaultMethod::method-updated');
    }

    public function render()
    {
        return render('components.livewire.update-default-payment-method');
    }
}
