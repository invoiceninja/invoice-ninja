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

use App\Jobs\Brevo\ProcessBrevoInboundWebhook;
use App\Jobs\Brevo\ProcessBrevoWebhook;
use Illuminate\Http\Request;

/**
 * Class PostMarkController.
 */
class BrevoController extends BaseController
{
    public function __construct()
    {
    }

    /**
     * Process Brevo Webhook.
     *
     *
     * @OA\Post(
     *      path="/api/v1/brevo_webhook",
     *      operationId="brevoWebhook",
     *      tags={"brevo"},
     *      summary="Processing webhooks from Brevo",
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
        if ($request->has('token') && $request->get('token') == config('services.brevo.secret')) {
            ProcessBrevoWebhook::dispatch($request->all())->delay(rand(2, 10));

            return response()->json(['message' => 'Success'], 200);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }


    /**
     * Process Brevo Inbound Webhook.
     *
     * IMPORTANT NOTICE: brevo strips old sended emails, therefore only current attachements are present
     *
     * IMPORTANT NOTICE: brevo saves the message and attachemnts for later retrieval, therefore we can process it within a async job for performance reasons
     *
     * @OA\Post(
     *      path="/api/v1/brevo_inbound_webhook",
     *      operationId="brevoInboundWebhook",
     *      tags={"brevo"},
     *      summary="Processing inbound webhooks from Brevo",
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
     *
     * array (
     *     'items' =>
     *     array (
     *         0 =>
     *         array (
     *         'Uuid' =>
     *         array (
     *             0 => 'd9f48d52-a344-42a4-9056-9733488d9fa3',
     *         ),
     *         'Recipients' =>
     *         array (
     *             0 => 'test@test.de',
     *         ),
     *         'MessageId' => '<CADfEuNvumhUdqAUa0j6MxzVp0ooMYqdb_KZ7nZqHNAfdDqwWEQ@mail.gmail.com>',
     *         'InReplyTo' => NULL,
     *         'From' =>
     *         array (
     *             'Name' => 'Max Mustermann',
     *             'Address' => 'max@mustermann.de',
     *         ),
     *         'To' =>
     *         array (
     *             0 =>
     *             array (
     *             'Name' => NULL,
     *             'Address' => 'test@test.de',
     *             ),
     *         ),
     *         'Cc' =>
     *         array (
     *         ),
     *         'Bcc' =>
     *         array (
     *         ),
     *         'ReplyTo' => NULL,
     *         'SentAtDate' => 'Sat, 23 Mar 2024 18:18:20 +0100',
     *         'Subject' => 'TEST',
     *         'Attachments' =>
     *         array (
     *             0 =>
     *             array (
     *             'Name' => 'flag--sv-1x1.svg',
     *             'ContentType' => 'image/svg+xml',
     *             'ContentLength' => 79957,
     *             'ContentID' => 'f_lu4ct6s20',
     *             'DownloadToken' => 'eyJmb2xkZXIiOiIyMDI0MDMyMzE3MTgzNi45OS43OTgwMDM4MDQiLCJmaWxlbmFtZSI6ImZsYWctLXN2LTF4MS5zdmcifQ',
     *             ),
     *         ),
     *         'Headers' =>
     *         array (
     *             'Received' => 'by mail-ed1-f51.google.com with SMTP id 4fb4d7f45d1cf-56b0af675deso3877288a12.1 for <test@test.de>; Sat, 23 Mar 2024 10:18:36 -0700 (PDT)',
     *             'DKIM-Signature' => 'v=1; a=rsa-sha256; c=relaxed/relaxed; d=mustermann.de; s=google; t=1711214316; x=1711819116; darn=test.de; h=to:subject:message-id:date:from:mime-version:from:to:cc:subject :date:message-id:reply-to; bh=eBSl5M0zvmTd+dFXGXMMSWrQ4nCvUdyVx+1Xpl+YuX8=; b=ackw3d+qTvZk4JKxomvH626MvfwmH23mikOUc2hWwYiO6unmQgPs2w5spnkmD9aCZ9 G+3nPSYKntugOmqWstZH3z4B063U4Y6j5hTc19WtCyyb9UR+XD+C6L10yc6ez8QUhlZT uAGqDoJ+E8+dBxiMul2pow19lC88t3QxRXU+i8zScniV7SFkwzziCEODaB61yI0DXsZB bUkx5Gx6cztKaNVF2QgguF2nQnJFUnD2nabVFsihyJ5r6y61rkSM/YTfMJuES772lnhv IeF+vwiFNEPKafrchce6YJcvo5Vd5lYFK4LtHyCy3mwJpX2QY+WnWAfferZ2YfgEL0Sf K3Pw==',
     *             'X-Google-DKIM-Signature' => 'v=1; a=rsa-sha256; c=relaxed/relaxed; d=1e100.net; s=20230601; t=1711214316; x=1711819116; h=to:subject:message-id:date:from:mime-version:x-gm-message-state :from:to:cc:subject:date:message-id:reply-to; bh=eBSl5M0zvmTd+dFXGXMMSWrQ4nCvUdyVx+1Xpl+YuX8=; b=fg4tXZnstRBexYlC6MD7C7is0kQj+xY66cSJ78tSa7PtSFQzY0zajDMsepMCGiiWmN /Pc/tRtk53pru/OtfzRT9pbM6mhM1arIt+QaQBQGU5xZVV5JXfPmdnPzXqAbQztyeHrk UcEkz+qDN3JNoidw2dJhhdt5MxdKssR572NwtBrn/rN7f1o/ThWzEz+P0o06GVBpxVYP wM0EkvcJj2SUOcn36kmp1ccbMUwYCU2h1JmniEFY8RTqu2il13iXoBvG4YPxe0c0hJ6z zw1N5rONeQM113N1rpbQzS1QLSngczuOhN24M3TOwrHJIec/BxrOW6KWl/uPUqiZAf65 f0tg==',
     *             'X-Gm-Message-State' => 'AOJu0YzKhR1HY1oUXoq++LLpl6UOz1S60NfPxuPXBLcP+6aACYle8rqQ fYHe2rQYTpg4KWiOswu858STOW8qmiewXD6gH/LbmEFs7sknRyDPNr/+L0cv828A3o+SOvXu3uP SY6H1aNSwIpqTRhJ+nNjTuSUpuSoABd9fYXFwPuivV0DtBhoVmpE=',
     *             'X-Google-Smtp-Source' => 'AGHT+IHdA9ZhW0dQxgOYx2OXBGmu4pzSR/zwJ0vcPNXFSqttKCPS2oTw1a9b2mMdhyUeoRAwP5TmhHlAtqUUrOPwkgg=',
     *             'X-Received' => 'by 2002:a50:d74c:0:b0:567:3c07:8bbc with SMTP id i12-20020a50d74c000000b005673c078bbcmr2126401edj.21.1711214316135; Sat, 23 Mar 2024 10:18:36 -0700 (PDT)',
     *             'MIME-Version' => '1.0',
     *             'From' => 'Max Mustermann <max@mustermann.de>',
     *             'Date' => 'Sat, 23 Mar 2024 18:18:20 +0100',
     *             'Message-ID' => '<CADfEuNvumhUdqAUa0j6MxzVp0ooMYqdb_KZ7nZqHNAfdDqwWEQ@mail.gmail.com>',
     *             'Subject' => 'TEST',
     *             'To' => 'test@test.de',
     *             'Content-Type' => 'multipart/mixed',
     *         ),
     *         'SpamScore' => 2.8,
     *         'ExtractedMarkdownMessage' => 'TEST',
     *         'ExtractedMarkdownSignature' => NULL,
     *         'RawHtmlBody' => '<div dir="ltr">TEST</div>',
     *         'RawTextBody' => 'TEST',
     *         'EMLDownloadToken' => 'eyJmb2xkZXIiOiIyMDI0MDMyMzE3MTgzNi45OS43OTgwMDM4MDQiLCJmaWxlbmFtZSI6InNtdHAuZW1sIn0',
     *         ),
     *     ),
     *   )
     */
    public function inboundWebhook(Request $request)
    {
        $input = $request->all();

        if (!($request->has('token') && $request->get('token') == config('ninja.inbound_mailbox.inbound_webhook_token'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!array_key_exists('items', $input)) {
            nlog('Failed: Message could not be parsed, because required parameters are missing.');
            return response()->json(['message' => 'Failed. Invalid Parameters.'], 400);
        }

        foreach ($input["items"] as $item) {

            if (!array_key_exists('Recipients', $item) || !array_key_exists('MessageId', $item)) {
                nlog('Failed: Message could not be parsed, because required parameters are missing. At least one item was invalid.');
                return response()->json(['message' => 'Failed. Invalid Parameters. At least one item was invalid.'], 400);
            }

            ProcessBrevoInboundWebhook::dispatch($item)->delay(rand(2, 10));

        }

        return response()->json(['message' => 'Success'], 201);
    }
}
