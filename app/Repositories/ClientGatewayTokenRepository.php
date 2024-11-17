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

namespace App\Repositories;

use App\Models\ClientGatewayToken;

/**
 * Class for ClientGatewayTokenRepository .
 */
class ClientGatewayTokenRepository extends BaseRepository
{
    public function save(array $data, ClientGatewayToken $client_gateway_token): ClientGatewayToken
    {
        $client_gateway_token->fill($data);
        
        if(isset($data['company_gateway_id']))
            $client_gateway_token->company_gateway_id = $data['company_gateway_id'];
                    
        if (isset($data['is_default']) && !boolval($data['is_default'])) {
            $client_gateway_token->is_default = false;
        }

        $client_gateway_token->save();

        if (isset($data['is_default']) && boolval($data['is_default'])) {
            return $this->setDefault($client_gateway_token);
        }

        return $client_gateway_token->fresh();
    }

    public function setDefault(ClientGatewayToken $client_gateway_token): ClientGatewayToken
    {
        ClientGatewayToken::withTrashed()
                            ->where('company_id', $client_gateway_token->company_id)
                            ->where('client_id', $client_gateway_token->client_id)
                            ->update(['is_default' => false]);

        $client_gateway_token->is_default = true;
        $client_gateway_token->save();

        return $client_gateway_token->fresh();
    }
}
