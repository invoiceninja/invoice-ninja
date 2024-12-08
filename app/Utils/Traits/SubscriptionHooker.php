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

namespace App\Utils\Traits;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

/**
 * Class SubscriptionHooker.
 */
trait SubscriptionHooker
{
    public function sendLoad($subscription, $body)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        if (!isset($subscription->webhook_configuration['post_purchase_url']) && !isset($subscription->webhook_configuration['post_purchase_rest_method'])) {
            return [];
        }

        if (count($subscription->webhook_configuration['post_purchase_headers']) >= 1) {
            $headers = array_merge($headers, $subscription->webhook_configuration['post_purchase_headers']);
        }

        $client = new \GuzzleHttp\Client(
            [
                'headers' => $headers,
            ]
        );

        $post_purchase_rest_method = (string) $subscription->webhook_configuration['post_purchase_rest_method'];
        $post_purchase_url = (string) $subscription->webhook_configuration['post_purchase_url'];

        try {
            $response = $client->{$post_purchase_rest_method}($post_purchase_url, [
                RequestOptions::JSON => ['body' => $body], RequestOptions::ALLOW_REDIRECTS => false,
            ]);

            if ($response_body = json_decode($response->getBody(), true)) {
                return array_merge($body, $response_body);
            }

            return array_merge($body, ['message' => 'Success', 'status_code' => 200]);

        } catch (ClientException $e) {
            $message = $e->getMessage();

            $error = json_decode($e->getResponse()->getBody()->getContents());

            if (is_null($error)) {
                nlog("empty response");
                nlog($e->getMessage());
            }

            if ($error && property_exists($error, 'message')) {
                $message = $error->message;
            }

            return array_merge($body, ['message' => $message, 'status_code' => 500]);
        } catch (\Exception $e) {
            return array_merge($body, ['message' => $e->getMessage(), 'status_code' => 500]);
        }
    }
}
