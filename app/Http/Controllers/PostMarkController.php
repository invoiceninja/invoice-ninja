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

use App\Jobs\PostMark\ProcessPostmarkWebhook;
use App\Libraries\MultiDB;
use App\Services\InboundMail\InboundMail;
use App\Services\InboundMail\InboundMailEngine;
use App\Utils\TempFile;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

/**
 * Class PostMarkController.
 */
class PostMarkController extends BaseController
{
    public function __construct()
    {
    }

    /**
     * Process Postmark Webhook.
     *
     *
     * @OA\Post(
     *      path="/api/v1/postmark_webhook",
     *      operationId="postmarkWebhook",
     *      tags={"postmark"},
     *      summary="Processing webhooks from PostMark",
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
        if ($request->header('X-API-SECURITY') && $request->header('X-API-SECURITY') == config('services.postmark.token')) {
            ProcessPostmarkWebhook::dispatch($request->all())->delay(15);

            return response()->json(['message' => 'Success'], 200);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }

    /**
     * Process Postmark Webhook.
     *
     * IMPORTANT NOTICE: postmark does NOT strip old sended emails, therefore also all past attachements are present
     *
     * IMPORTANT NOTICE: postmark does not saves attachements for later retrieval, therefore we cannot process it within a async job
     *
     * @OA\Post(
     *      path="/api/v1/postmark_inbound_webhook",
     *      operationId="postmarkInboundWebhook",
     *      tags={"postmark"},
     *      summary="Processing inbound webhooks from PostMark",
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
     *   'FromName' => 'Max Mustermann',
     *   'MessageStream' => 'inbound',
     *   'From' => 'max@mustermann.de',
     *   'FromFull' =>
     *   array (
     *     'Email' => 'max@mustermann.de',
     *     'Name' => 'Max Mustermann',
     *     'MailboxHash' => NULL,
     *   ),
     *   'To' => '370c69ad9e41d616fc914b3c60250224@inbound.postmarkapp.com',
     *   'ToFull' =>
     *   array (
     *     0 =>
     *     array (
     *       'Email' => '370c69ad9e41d616fc914b3c60250224@inbound.postmarkapp.com',
     *       'Name' => NULL,
     *       'MailboxHash' => NULL,
     *     ),
     *   ),
     *   'Cc' => NULL,
     *   'CcFull' =>
     *   array (
     *   ),
     *   'Bcc' => NULL,
     *   'BccFull' =>
     *   array (
     *   ),
     *   'OriginalRecipient' => '370c69ad9e41d616fc914b3c60250224@inbound.postmarkapp.com',
     *   'Subject' => 'Re: adaw',
     *   'MessageID' => 'd37fde00-b4cf-4b64-ac64-e9f6da523c25',
     *   'ReplyTo' => NULL,
     *   'MailboxHash' => NULL,
     *   'Date' => 'Sun, 24 Mar 2024 13:17:52 +0100',
     *   'TextBody' => 'wadwad
     *
     *   Am So., 24. März 2024 um 13:17 Uhr schrieb Max Mustermann <max@mustermann.de>:
     *
     *   > test
     *   >
     *
     *   --
     *   test.de - Max Mustermann <https://test.de/>kontakt@test.de
     *   <mailto:kontakt@test.de>',
     *   'HtmlBody' => '<div dir="ltr">wadwad</div><br><div class="gmail_quote"><div dir="ltr" class="gmail_attr">Am So., 24. März 2024 um 13:17 Uhr schrieb Max Mustermann &lt;<a href="mailto:max@mustermann.de">max@mustermann.de</a>&gt;:<br></div><blockquote class="gmail_quote" style="margin:0px 0px 0px 0.8ex;border-left:1px solid rgb(204,204,204);padding-left:1ex"><div dir="ltr">test</div>
     *   </blockquote></div>
     *
     *   <br>
     *   <font size="3"><a href="https://test.de/" target="_blank">test.de - Max Mustermann</a></font><div><a href="mailto:kontakt@test.de" style="font-size:medium" target="_blank">kontakt@test.de</a><br></div>',
     *   'StrippedTextReply' => 'wadwad
     *
     *   Am So., 24. März 2024 um 13:17 Uhr schrieb Max Mustermann <max@mustermann.de>:',
     *   'Tag' => NULL,
     *   'Headers' =>
     *   array (
     *     0 =>
     *     array (
     *       'Name' => 'Return-Path',
     *       'Value' => '<max@mustermann.de>',
     *     ),
     *     1 =>
     *     array (
     *       'Name' => 'Received',
     *       'Value' => 'by p-pm-inboundg02a-aws-euwest1a.inbound.postmarkapp.com (Postfix, from userid 996)	id 8ED1A453CA4; Sun, 24 Mar 2024 12:18:10 +0000 (UTC)',
     *     ),
     *     2 =>
     *     array (
     *       'Name' => 'X-Spam-Checker-Version',
     *       'Value' => 'SpamAssassin 3.4.0 (2014-02-07) on	p-pm-inboundg02a-aws-euwest1a',
     *     ),
     *     3 =>
     *     array (
     *       'Name' => 'X-Spam-Status',
     *       'Value' => 'No',
     *     ),
     *     4 =>
     *     array (
     *       'Name' => 'X-Spam-Score',
     *       'Value' => '-0.1',
     *     ),
     *     5 =>
     *     array (
     *       'Name' => 'X-Spam-Tests',
     *       'Value' => 'DKIM_SIGNED,DKIM_VALID,DKIM_VALID_AU,HTML_MESSAGE,	RCVD_IN_DNSWL_NONE,RCVD_IN_MSPIKE_H2,RCVD_IN_ZEN_BLOCKED_OPENDNS,	SPF_HELO_NONE,SPF_PASS,URIBL_DBL_BLOCKED_OPENDNS,URIBL_ZEN_BLOCKED_OPENDNS',
     *     ),
     *     6 =>
     *     array (
     *       'Name' => 'Received-SPF',
     *       'Value' => 'pass (test.de: Sender is authorized to use \'max@mustermann.de\' in \'mfrom\' identity (mechanism \'include:_spf.google.com\' matched)) receiver=p-pm-inboundg02a-aws-euwest1a; identity=mailfrom; envelope-from="max@mustermann.de"; helo=mail-lf1-f51.google.com; client-ip=209.85.167.51',
     *     ),
     *     7 =>
     *     array (
     *       'Name' => 'Received',
     *       'Value' => 'from mail-lf1-f51.google.com (mail-lf1-f51.google.com [209.85.167.51])	(using TLSv1.2 with cipher ECDHE-RSA-AES128-GCM-SHA256 (128/128 bits))	(No client certificate requested)	by p-pm-inboundg02a-aws-euwest1a.inbound.postmarkapp.com (Postfix) with ESMTPS id 437BD453CA2	for <370c69ad9e41d616fc914b3c60250224@inbound.postmarkapp.com>; Sun, 24 Mar 2024 12:18:10 +0000 (UTC)',
     *     ),
     *     8 =>
     *     array (
     *       'Name' => 'Received',
     *       'Value' => 'by mail-lf1-f51.google.com with SMTP id 2adb3069b0e04-513cf9bacf1so4773866e87.0        for <370c69ad9e41d616fc914b3c60250224@inbound.postmarkapp.com>; Sun, 24 Mar 2024 05:18:10 -0700 (PDT)',
     *     ),
     *     9 =>
     *     array (
     *       'Name' => 'DKIM-Signature',
     *       'Value' => 'v=1; a=rsa-sha256; c=relaxed/relaxed;        d=test.de; s=google; t=1711282689; x=1711887489; darn=inbound.postmarkapp.com;        h=to:subject:message-id:date:from:in-reply-to:references:mime-version         :from:to:cc:subject:date:message-id:reply-to;        bh=NvjmqLXF/5L5ZrpToR/6FgVOhTOGC9j0/B2Na5Ke6J8=;        b=AMXIEoh6yGrOT6X3eBBClQ3NXFNuEoqxeM6aPONsqbpShAcT24iAJmqXylaLHv3fyX         Hm6mwp3a029NnrLP/VRyKZbzIMBN2iycidtrEMXF/Eg2e42Q/08/2dZ7nxH6NqE/jz01         3M7qvwHvuoZ2Knhj7rnZc6I5m/nFxBsZc++Aj0Vv9sFoWZZooqAeTXbux1I5NyE17MrL         D6byca43iINARZN7XOkoChRRZoZbOqZEtc2Va5yw7v+aYguLB4HHrIFC7G+L8hAJ0IAo         3R3DFeBw58M1xtxXCREI8Y6qMQTw60XyFw0gVmZzqR4hZiTerBSJJsZLZOBgmXxq3WLS         +xVQ==',
     *     ),
     *     10 =>
     *     array (
     *       'Name' => 'X-Google-DKIM-Signature',
     *       'Value' => 'v=1; a=rsa-sha256; c=relaxed/relaxed;        d=1e100.net; s=20230601; t=1711282689; x=1711887489;        h=to:subject:message-id:date:from:in-reply-to:references:mime-version         :x-gm-message-state:from:to:cc:subject:date:message-id:reply-to;        bh=NvjmqLXF/5L5ZrpToR/6FgVOhTOGC9j0/B2Na5Ke6J8=;        b=uKoMhir+MX/wycNEr29Sffj45ooKksCJ1OfSRkIIGHk0rnHn8Vh+c7beYipwRPW4F2         h46K64vtIX00guYMdL2Qo2eY96+wALTqHCy67PGhvotVTROz21yxjx62pCDPGs5tefOu         IkyxoybpIK8zAfLoDTd9p2GIrr5brKJyB2w1NQc1htxTQ5D4RgBxUAOKv4uVEr8r47iA         MIo5d8/AifA+vCOAh7iJ7EmvDQ1R+guhQyH9m1Jo8PLapiYuHXggpBJvooyGuflKqbnt         gJ/dscEr4d5aWJbw/x1dmIJ5gyJPGdBWq8NRqV/qbkXQW3H/gylifDUPXbki+EQBD5Yu         EuLQ==',
     *     ),
     *     11 =>
     *     array (
     *       'Name' => 'X-Gm-Message-State',
     *       'Value' => 'AOJu0Yxpbp1sRh17lNzg+pLnIx1jCn8ZFJQMgFuHK+6Z8RqFS5KKKTxR	8onpEbxWYYVUbrJFExNBHPD/3jdxqifCVVNaDmbpwHgmW5lHLJmA5vYRq5NFZ9OA6zKx/N6Gipr	iXE4fXmSqghFNTzy9V/RT08Zp+F5RiFh/Ta6ltQl8XfCPFfSawLz6cagUgt8bBuF4RqdrYmWwzj	ty86V5Br1htRNEFYivoXnNmaRcsD0tca1D23ny62O6RwWugrj1IpAYhViNyTZAWu+loKgfjJJoI	MsyiSU=',
     *     ),
     *     12 =>
     *     array (
     *       'Name' => 'X-Google-Smtp-Source',
     *       'Value' => 'AGHT+IEdtZqbVI6j7WLeaSL3dABGSnWIXaSjbYqXvFvE2H+f2zsn0gknQ4OdTJecQRCabpypVF2ue91Jb7aKl6RiyEQ=',
     *     ),
     *     13 =>
     *     array (
     *       'Name' => 'X-Received',
     *       'Value' => 'by 2002:a19:385a:0:b0:513:c876:c80a with SMTP id d26-20020a19385a000000b00513c876c80amr2586776lfj.34.1711282689140; Sun, 24 Mar 2024 05:18:09 -0700 (PDT)',
     *     ),
     *     14 =>
     *     array (
     *       'Name' => 'MIME-Version',
     *       'Value' => '1.0',
     *     ),
     *     15 =>
     *     array (
     *       'Name' => 'References',
     *       'Value' => '<CADfEuNsNFmNNCJDPjpS36amoLv2XEm41HmgYJT7Tj=R96PkxnA@mail.gmail.com>',
     *     ),
     *     16 =>
     *     array (
     *       'Name' => 'In-Reply-To',
     *       'Value' => '<CADfEuNsNFmNNCJDPjpS36amoLv2XEm41HmgYJT7Tj=R96PkxnA@mail.gmail.com>',
     *     ),
     *     17 =>
     *     array (
     *       'Name' => 'Message-ID',
     *       'Value' => '<CADfEuNvyCLsnp=CwJ3BF=-L6rn=o+DmUOPP6Cp4F-SO0p0hVwQ@mail.gmail.com>',
     *     ),
     *   ),
     *   'Attachments' =>
     *   array (
     *      array (
     *          'Content' => "base64-String",
     *          'ContentLength' => 60164,
     *          'Name' => 'Unbenannt.png',
     *          'ContentType' => 'image/png',
     *          'ContentID' => 'ii_luh2h8lg0',
     *      )
     *   ),
     *  )
     */
    public function inboundWebhook(Request $request)
    {

        $input = $request->all();

        if (!$request->has('token') || $request->token != config('ninja.inbound_mailbox.inbound_webhook_token')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!(array_key_exists("MessageStream", $input) && $input["MessageStream"] == "inbound") || !array_key_exists("To", $input) || !array_key_exists("From", $input) || !array_key_exists("MessageID", $input)) {
            nlog('Failed: Message could not be parsed, because required parameters are missing.');
            return response()->json(['message' => 'Failed. Missing/Invalid Parameters.'], 400);
        }

        $company = MultiDB::findAndSetDbByExpenseMailbox($input["ToFull"][0]["Email"]);

        if (!$company) {
            nlog('[PostmarkInboundWebhook] unknown Expense Mailbox occured while handling an inbound email from postmark: ' . $input["To"]);
            return response()->json(['message' => 'Ok'], 200);
        }

        $inboundEngine = new InboundMailEngine($company);

        if ($inboundEngine->isInvalidOrBlocked($input["From"], $input["ToFull"][0]["Email"])) {
            return response()->json(['message' => 'Blocked.'], 403);
        }

        try { // important to save meta if something fails here to prevent spam

            // prepare data for ingresEngine
            $inboundMail = new InboundMail();

            $inboundMail->from = $input["From"] ?? '';
            $inboundMail->to = $input["To"]; // usage of data-input, because we need a single email here
            $inboundMail->subject = $input["Subject"] ?? '';
            $inboundMail->body = $input["HtmlBody"] ?? '';
            $inboundMail->text_body = $input["TextBody"] ?? '';
            $inboundMail->date = Carbon::createFromTimeString($input["Date"]);

            // parse documents as UploadedFile from webhook-data
            foreach ($input["Attachments"] as $attachment) {

                $inboundMail->documents[] = TempFile::UploadedFileFromBase64($attachment["Content"], $attachment["Name"], $attachment["ContentType"]);

            }

        } catch (\Exception $e) {
            $inboundEngine->saveMeta($input["From"], $input["To"]); // important to save this, to protect from spam
            throw $e;
        }

        // perform
        try {

            $inboundEngine->handleExpenseMailbox($inboundMail);

        } catch (\Exception $e) {
            if ($e->getCode() == 409) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            throw $e;
        }

        return response()->json(['message' => 'Success'], 200);
    }
}
