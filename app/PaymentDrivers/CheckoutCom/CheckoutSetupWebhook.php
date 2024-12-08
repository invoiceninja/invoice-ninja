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

namespace App\PaymentDrivers\CheckoutCom;

use App\Libraries\MultiDB;
use App\Models\CompanyGateway;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use App\PaymentDrivers\Stripe\Utilities;
use Checkout\CheckoutApiException;
use Checkout\CheckoutAuthorizationException;
use Checkout\Workflows\Actions\WebhookSignature;
use Checkout\Workflows\Actions\WebhookWorkflowActionRequest;
use Checkout\Workflows\Conditions\EventWorkflowConditionRequest;
use Checkout\Workflows\CreateWorkflowRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckoutSetupWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Utilities;

    public $tries = 1;

    public $deleteWhenMissingModels = true;

    private string $authentication_webhook_name = 'Invoice_Ninja_3DS_Workflow';

    public CheckoutComPaymentDriver $checkout;

    public function __construct(private string $company_key, private int $company_gateway_id)
    {
    }

    public function handle()
    {

        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        /** @var \App\Models\CompanyGateway $company_gateway */
        $company_gateway = CompanyGateway::find($this->company_gateway_id);

        $this->checkout = $company_gateway->driver()->init();

        $webhook = new Webhook($this->checkout);

        $workflows = $webhook->getWorkFlows();

        $wf = collect($workflows['data'])->first(function ($workflow) {
            return $workflow['name'] == $this->authentication_webhook_name;
        });

        if ($wf) {
            return;
        }

        $this->createAuthenticationWorkflow();
    }

    /**
     * Creates an authentication workflow for 3DS
     * and also a registration mechanism for payments that have been approved.
     *
     * @return void
     */
    public function createAuthenticationWorkflow()
    {

        $signature = new WebhookSignature();
        $signature->key = $this->checkout->company_gateway->company->company_key;
        $signature->method = "HMACSHA256";

        $actionRequest = new WebhookWorkflowActionRequest();
        $actionRequest->url = $this->checkout->company_gateway->webhookUrl();
        $actionRequest->signature = $signature;

        $eventWorkflowConditionRequest = new EventWorkflowConditionRequest();
        $eventWorkflowConditionRequest->events = [
            "gateway" => ["payment_approved"],
            "issuing" => ["authorization_approved","authorization_declined"],
        ];

        $request = new CreateWorkflowRequest();
        $request->actions = [$actionRequest];
        $request->conditions = [$eventWorkflowConditionRequest];
        $request->name = $this->authentication_webhook_name;
        $request->active = true;

        try {
            $response = $this->checkout->gateway->getWorkflowsClient()->createWorkflow($request);

        } catch (CheckoutApiException $e) {
            // API error
            $error_details = $e->error_details;
            $http_status_code = isset($e->http_metadata) ? $e->http_metadata->getStatusCode() : null;
            nlog("Checkout WEBHOOK creation error");
            nlog($error_details);
        } catch (CheckoutAuthorizationException $e) {
            // Bad Invalid authorization
        }

    }



}
