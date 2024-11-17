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

namespace App\Http\Controllers;

use App\Utils\Ninja;
use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Http\Request;
use App\Utils\Traits\SavesDocuments;
use App\Jobs\Mailgun\ProcessMailgunWebhook;
use App\Jobs\Mailgun\ProcessMailgunInboundWebhook;

/**
 * Class MailgunController.
 */
class MailgunController extends BaseController
{
    use SavesDocuments;

    public function __construct()
    {
    }

    /**
     * Process Mailgun Webhook.
     *
     *
     * @OA\Post(
     *      path="/api/v1/mailgun_webhook",
     *      operationId="mailgunWebhook",
     *      tags={"mailgun"},
     *      summary="Processing webhooks from Mailgun",
     *      description="Adds an credit to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved credit object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function webhook(Request $request)
    {

        $input = $request->all();
        
        nlog($input);

        if (\abs(\time() - $request['signature']['timestamp']) > 15) {
            return response()->json(['message' => 'Success'], 200);
        }

        if (\hash_equals(\hash_hmac('sha256', $input['signature']['timestamp'] . $input['signature']['token'], config('services.mailgun.webhook_signing_key')), $input['signature']['signature'])) {
            ProcessMailgunWebhook::dispatch($request->all())->delay(rand(2, 10));
        }

        return response()->json(['message' => 'Success.'], 200);
    }

    /**
     * Process Mailgun Inbound Webhook.
     *
     * IMPORTANT NOTICE: mailgun does NOT strip old sended emails, therefore all past attachements are present
     *
     * IMPORTANT NOTICE: mailgun saves the message and attachemnts for later retrieval, therefore we can process it within a async job for performance reasons
     *
     *
     * @OA\Post(
     *      path="/api/v1/mailgun_inbound_webhook",
     *      operationId="mailgunInboundWebhook",
     *      tags={"mailgun"},
     *      summary="Processing inbound webhooks from Mailgun",
     *      description="Adds an credit to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved credit object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function inboundWebhook(Request $request)
    {
        $input = $request->all();

        $authorizedByHash = \hash_equals(\hash_hmac('sha256', $input['timestamp'] . $input['token'], config('services.mailgun.webhook_signing_key')), $input['signature']);
        $authorizedByToken = $request->has('token') && $request->get('token') == config('ninja.inbound_mailbox.inbound_webhook_token');
        if (!$authorizedByHash && !$authorizedByToken) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        nlog($input);

        if (!array_key_exists('sender', $input) || !array_key_exists('recipient', $input) || !array_key_exists('message-url', $input)) {
            nlog('Failed: Message could not be parsed, because required parameters are missing. Please ensure contacting this api-endpoint with a store & notify operation instead of a forward operation!');
            return response()->json(['message' => 'Failed. Missing Parameters. Use store and notify!'], 400);
        }
        
        /** @var \App\Models\Company $company */
        $company = MultiDB::findAndSetDbByExpenseMailbox($input["recipient"]);

        if(!$company)
            return response()->json(['message' => 'Ok'], 200);  // Fail gracefully

        ProcessMailgunInboundWebhook::dispatch($input["sender"], $input["recipient"], $input["message-url"], $company)->delay(rand(2, 10));

        return response()->json(['message' => 'Success.'], 200);
    }
}
