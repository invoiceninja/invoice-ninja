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

namespace App\Services\EDocument\Gateway\Qvalia;

class Invoice
{
    public function __construct(public Qvalia $qvalia)
    {
    }

    // Methods
    /**
     * status
     *
     * @param  string $legal_entity_id
     * @param  string $integration_id
     * @return mixed
     */

    //  {
    //     "status": "",
    //     "data": {
    //         "message": "",
    //         "status": {
    //         "document_id": "",
    //         "order_number": "",
    //         "payment_reference": "",
    //         "credit_note": "",
    //         "reminder": "",
    //         "status": "",
    //         "sent_at": "",
    //         "paid_at": "",
    //         "cancelled_at": "",
    //         "send_method": ""
    //         }
    //     }
    // }

    /**
     * status
     *
     * @param  string $legal_entity_id
     * @param  string $integration_id
     * @return mixed
     */
    public function status(string $legal_entity_id, string $integration_id)
    {
        $uri = "/account/{$legal_entity_id}/action/invoice/outgoing/status/{$integration_id}";

        $r = $this->qvalia->httpClient($uri, (\App\Enum\HttpVerb::GET)->value, []);

        return $r->object();
    }

    /**
     * send
     *
     * @param  string $legal_entity_id
     * @param  string $document
     * @return mixed
     */
    public function send(string $legal_entity_id, string $document)
    {
        // Set Headers
        // Either "application/json" (default) or "application/xml"

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            // 'Content-Type' => 'application/xml',
        ];

        $data = [
            'Invoice' => $document
        ];

        $uri = "/transaction/{$legal_entity_id}/invoices/outgoing";

        $r = $this->qvalia->httpClient($uri, (\App\Enum\HttpVerb::POST)->value, $data, $headers);

        return $r->object();

    }
}
