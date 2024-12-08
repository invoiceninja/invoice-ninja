<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Mailgun;

use App\Models\Company;
use App\Utils\TempFile;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\InboundMail\InboundMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\InboundMail\InboundMailEngine;

class ProcessMailgunInboundWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    private InboundMailEngine $engine;

    /**
     * Create a new job instance.
     * $input consists of 3 informations: sender/from|recipient/to|messageUrl
     */
    public function __construct(private string $sender, private string $recipient, private string $message_url, private Company $company)
    {
        $this->engine = new InboundMailEngine($company);
    }

    /**
     * Execute the job.
     *
     * IMPORTANT NOTICE: mailgun does NOT strip old sended emails, therefore all past attachements are present
     *
     * Mail from Storage
     * {
     *   "Content-Type": "multipart/related; boundary=\"00000000000022bfbe0613e8b7f5\"",
     *   "Date": "Mon, 18 Mar 2024 06:34:09 +0100",
     *   "Dkim-Signature": "v=1; a=rsa-sha256; c=relaxed/relaxed; d=wer-ner.de; s=google; t=1710740086; x=1711344886; darn=domain.example; h=to:subject:message-id:date:from:in-reply-to:references:mime-version :from:to:cc:subject:date:message-id:reply-to; bh=tkxC+ZzDSJJXLVgjDyvQZyDt6wkWKFHS50z4ZWiWT9U=; b=P1Sz54Djj1LHtPF7+cAKGRaN4IRjUT3bOyYAD/kbC0Tx2yNejPrjCPy3+a6R6MShgJ odYhoLRqylPPs1DQolNO6xgamsoEiR8jnII4QjJUBut4VirMlSO+RLxzpO7pt/Hr6j93 z0G1Yffpbz44l5GhndgXsa4Hf30Q8yy0p7fqMNABB/smscj7DJDu1os2cB1JazKYsmAE X4HtU5IgCOS++xbQPqZSNwjrFWlbgal2t2yAeTKAMdGX/nNKtfgZ5imqNwJWerpAYwgk 3qvUcgTw2MpeghcPpTiflPGp4fT/f1kUjes0dcqrvkE+6oTPvo0pi76QNoVs7peWKr/c JvaA==",
     *   "From": "Paul Werner <test@sender.example>",
     *   "In-Reply-To": "<CADfEuNuk6m=RUmo+R4K65Rfskox_LOTT+pnBwTnmA_gPaf1eUQ@mail.gmail.com>",
     *   "Message-Id": "<CADfEuNu6nBJYqNSJ-suey3a0FazJELYkNSO5JUGiiGs9hsFvGg@mail.gmail.com>",
     *   "Mime-Version": "1.0",
     *   "Received": "by mail-lj1-f175.google.com with SMTP id 38308e7fff4ca-2d4a901e284so12524521fa.1 for <test@domain.example>; Sun, 17 Mar 2024 22:34:47 -0700 (PDT)",
     *   "References": "<CADfEuNvN_DcTU99WY-7332iPmPuYW-CfvJfirQ6YY30e3y-XeA@mail.gmail.com> <CADfEuNupjxUivY++FnD7b1SHdNY6YZCA9b4iVW6xNbmic=B6Zw@mail.gmail.com> <CADfEuNuk6m=RUmo+R4K65Rfskox_LOTT+pnBwTnmA_gPaf1eUQ@mail.gmail.com>",
     *   "Subject": "Fwd: TEST",
     *   "To": "test@domain.example",
     *   "X-Envelope-From": "test@sender.example",
     *   "X-Gm-Message-State": "AOJu0Yy6rgBIPLGjnD293mVB5vBWQIraVAOnfa/GtyM6S/JIqe4rHbrx OqRe7oFFyCDyCjL/+2AFFkB9ljxgt7MWvpdec69dEn3BNQMlxuyGkpyxZUY8PDm4XRCyIy4vGxK 6Oddl7nWV5DM4zN4eLvZH+DPteyUq9A9ET9bowZnCrP8ZcQOP5js=",
     *   "X-Google-Dkim-Signature": "v=1; a=rsa-sha256; c=relaxed/relaxed; d=1e100.net; s=20230601; t=1710740086; x=1711344886; h=to:subject:message-id:date:from:in-reply-to:references:mime-version :x-gm-message-state:from:to:cc:subject:date:message-id:reply-to; bh=tkxC+ZzDSJJXLVgjDyvQZyDt6wkWKFHS50z4ZWiWT9U=; b=cyDJAeNEaU2CvWAX/d9E3LMDrceXyLe01lbsYvwY6ZNTchr/0vzrxQFTVxos2DQR7u jSKpaNqI958H1oZJY36XZV0+8MY2w6DjB1F3FUHbD1q5gxJUitXNuOvvpna/q0ZaqlQf 5n3kIkakV19uxu4pcrcLxO67744pBzEmVk+IJtI9FEoZy9253v09CfkzNZo68u2VxJVD TDFVVkZuIO5xi3flUVoD3CP0Bw/0BqpDuxVvOFy+qOaItTZ5Na+OPfUJcFG2j6T0rXFQ 1vXPxodqjllLwc/V+O1TmS46H/RhsHGAae5tWk+51KX8T2ZgTkfwKPV1YeSRl0QtDhYS gU0Q==",
     *   "X-Google-Smtp-Source": "AGHT+IFspt+3tKf94kXs48nOb58GzuV+pJ8oE3ZNwEcx6PG53wJeW858lyh2PiYIzSEPQTY2ykatvu2fqs8Bj+9d5rw=",
     *   "X-Mailgun-Incoming": "Yes",
     *   "X-Received": "by 2002:a2e:9847:0:b0:2d4:7455:89f6 with SMTP id e7-20020a2e9847000000b002d4745589f6mr4283454ljj.40.1710740086045; Sun, 17 Mar 2024 22:34:46 -0700 (PDT)",
     *   "sender": "test@sender.example",
     *   "recipients": "test@domain.example",
     *   "from": "Paul Werner <test@sender.example>",
     *   "subject": "Fwd: TEST",
     *   "body-html": "<div dir=\"ltr\">TESTAGAIN<img src=\"cid:ii_ltwigc770\" alt=\"Unbenannt.png\" width=\"562\" height=\"408\"><br><br><div class=\"gmail_quote\"><div dir=\"ltr\" class=\"gmail_attr\">---------- Forwarded message ---------<br>Von: <strong class=\"gmail_sendername\" dir=\"auto\">Paul Werner</strong> <span dir=\"auto\">&lt;<a href=\"mailto:test@sender.example\">test@sender.example</a>&gt;</span><br>Date: Mo., 18. März 2024 um 06:30 Uhr<br>Subject: Fwd: TEST<br>To:  &lt;<a href=\"mailto:test@domain.example\">test@domain.example</a>&gt;<br></div><br><br><div dir=\"ltr\">Hallöööö<br><br><div class=\"gmail_quote\"><div dir=\"ltr\" class=\"gmail_attr\">---------- Forwarded message ---------<br>Von: <strong class=\"gmail_sendername\" dir=\"auto\">Paul Werner</strong> <span dir=\"auto\">&lt;<a href=\"mailto:test@sender.example\" target=\"_blank\">test@sender.example</a>&gt;</span><br>Date: Mo., 18. März 2024 um 06:23 Uhr<br>Subject: Fwd: TEST<br>To:  &lt;<a href=\"mailto:test@domain.example\" target=\"_blank\">test@domain.example</a>&gt;<br></div><br><br><div dir=\"ltr\">asjkdahwdaiohdawdawdawwwww!!!<br><br><div class=\"gmail_quote\"><div dir=\"ltr\" class=\"gmail_attr\">---------- Forwarded message ---------<br>Von: <strong class=\"gmail_sendername\" dir=\"auto\">Paul Werner</strong> <span dir=\"auto\">&lt;<a href=\"mailto:test@sender.example\" target=\"_blank\">test@sender.example</a>&gt;</span><br>Date: Mo., 18. März 2024 um 06:22 Uhr<br>Subject: TEST<br>To:  &lt;<a href=\"mailto:test@domain.example\" target=\"_blank\">test@domain.example</a>&gt;<br></div><br><br><div dir=\"ltr\">TEST</div>\r\n</div></div>\r\n</div></div>\r\n</div></div>\r\n",
     *   "body-plain": "TESTAGAIN[image: Unbenannt.png]\r\n\r\n---------- Forwarded message ---------\r\nVon: Paul Werner <test@sender.example>\r\nDate: Mo., 18. März 2024 um 06:30 Uhr\r\nSubject: Fwd: TEST\r\nTo: <test@domain.example>\r\n\r\n\r\nHallöööö\r\n\r\n---------- Forwarded message ---------\r\nVon: Paul Werner <test@sender.example>\r\nDate: Mo., 18. März 2024 um 06:23 Uhr\r\nSubject: Fwd: TEST\r\nTo: <test@domain.example>\r\n\r\n\r\nasjkdahwdaiohdawdawdawwwww!!!\r\n\r\n---------- Forwarded message ---------\r\nVon: Paul Werner <test@sender.example>\r\nDate: Mo., 18. März 2024 um 06:22 Uhr\r\nSubject: TEST\r\nTo: <test@domain.example>\r\n\r\n\r\nTEST\r\n",
     *   "attachments": [
     *       {
     *           "name": "Unbenannt.png",
     *           "content-type": "image/png",
     *           "size": 197753,
     *           "url": "https://storage-europe-west1.api.mailgun.net/v3/domains/domain.example/messages/BAAFAgVMamdcBboOIOtFyJ5B5NGEkkffYQ/attachments/0"
     *       }
     *   ],
     *   "content-id-map": {
     *       "<ii_ltwigc770>": {
     *           "name": "Unbenannt.png",
     *           "content-type": "image/png",
     *           "size": 197753,
     *           "url": "https://storage-europe-west1.api.mailgun.net/v3/domains/domain.example/messages/BAAFAgVMamdcBboOIOtFyJ5B5NGEkkffYQ/attachments/0"
     *       }
     *   },
     *   "message-headers": [
     *       [
     *           "Received",
     *           "from mail-lj1-f175.google.com (mail-lj1-f175.google.com [209.85.208.175]) by 634f26f73cf3 with SMTP id <undefined> (version=TLS1.3, cipher=TLS_AES_128_GCM_SHA256); Mon, 18 Mar 2024 05:34:47 GMT"
     *       ],
     *       [
     *           "Received",
     *           "by mail-lj1-f175.google.com with SMTP id 38308e7fff4ca-2d4a901e284so12524521fa.1 for <test@domain.example>; Sun, 17 Mar 2024 22:34:47 -0700 (PDT)"
     *       ],
     *       [
     *           "X-Envelope-From",
     *           "test@sender.example"
     *       ],
     *       [
     *           "X-Mailgun-Incoming",
     *           "Yes"
     *       ],
     *       [
     *           "Dkim-Signature",
     *           "v=1; a=rsa-sha256; c=relaxed/relaxed; d=wer-ner.de; s=google; t=1710740086; x=1711344886; darn=domain.example; h=to:subject:message-id:date:from:in-reply-to:references:mime-version :from:to:cc:subject:date:message-id:reply-to; bh=tkxC+ZzDSJJXLVgjDyvQZyDt6wkWKFHS50z4ZWiWT9U=; b=P1Sz54Djj1LHtPF7+cAKGRaN4IRjUT3bOyYAD/kbC0Tx2yNejPrjCPy3+a6R6MShgJ odYhoLRqylPPs1DQolNO6xgamsoEiR8jnII4QjJUBut4VirMlSO+RLxzpO7pt/Hr6j93 z0G1Yffpbz44l5GhndgXsa4Hf30Q8yy0p7fqMNABB/smscj7DJDu1os2cB1JazKYsmAE X4HtU5IgCOS++xbQPqZSNwjrFWlbgal2t2yAeTKAMdGX/nNKtfgZ5imqNwJWerpAYwgk 3qvUcgTw2MpeghcPpTiflPGp4fT/f1kUjes0dcqrvkE+6oTPvo0pi76QNoVs7peWKr/c JvaA=="
     *       ],
     *       [
     *           "X-Google-Dkim-Signature",
     *           "v=1; a=rsa-sha256; c=relaxed/relaxed; d=1e100.net; s=20230601; t=1710740086; x=1711344886; h=to:subject:message-id:date:from:in-reply-to:references:mime-version :x-gm-message-state:from:to:cc:subject:date:message-id:reply-to; bh=tkxC+ZzDSJJXLVgjDyvQZyDt6wkWKFHS50z4ZWiWT9U=; b=cyDJAeNEaU2CvWAX/d9E3LMDrceXyLe01lbsYvwY6ZNTchr/0vzrxQFTVxos2DQR7u jSKpaNqI958H1oZJY36XZV0+8MY2w6DjB1F3FUHbD1q5gxJUitXNuOvvpna/q0ZaqlQf 5n3kIkakV19uxu4pcrcLxO67744pBzEmVk+IJtI9FEoZy9253v09CfkzNZo68u2VxJVD TDFVVkZuIO5xi3flUVoD3CP0Bw/0BqpDuxVvOFy+qOaItTZ5Na+OPfUJcFG2j6T0rXFQ 1vXPxodqjllLwc/V+O1TmS46H/RhsHGAae5tWk+51KX8T2ZgTkfwKPV1YeSRl0QtDhYS gU0Q=="
     *       ],
     *       [
     *           "X-Gm-Message-State",
     *           "AOJu0Yy6rgBIPLGjnD293mVB5vBWQIraVAOnfa/GtyM6S/JIqe4rHbrx OqRe7oFFyCDyCjL/+2AFFkB9ljxgt7MWvpdec69dEn3BNQMlxuyGkpyxZUY8PDm4XRCyIy4vGxK 6Oddl7nWV5DM4zN4eLvZH+DPteyUq9A9ET9bowZnCrP8ZcQOP5js="
     *       ],
     *       [
     *           "X-Google-Smtp-Source",
     *           "AGHT+IFspt+3tKf94kXs48nOb58GzuV+pJ8oE3ZNwEcx6PG53wJeW858lyh2PiYIzSEPQTY2ykatvu2fqs8Bj+9d5rw="
     *       ],
     *       [
     *           "X-Received",
     *           "by 2002:a2e:9847:0:b0:2d4:7455:89f6 with SMTP id e7-20020a2e9847000000b002d4745589f6mr4283454ljj.40.1710740086045; Sun, 17 Mar 2024 22:34:46 -0700 (PDT)"
     *       ],
     *       [
     *           "Mime-Version",
     *           "1.0"
     *       ],
     *       [
     *           "References",
     *           "<CADfEuNvN_DcTU99WY-7332iPmPuYW-CfvJfirQ6YY30e3y-XeA@mail.gmail.com> <CADfEuNupjxUivY++FnD7b1SHdNY6YZCA9b4iVW6xNbmic=B6Zw@mail.gmail.com> <CADfEuNuk6m=RUmo+R4K65Rfskox_LOTT+pnBwTnmA_gPaf1eUQ@mail.gmail.com>"
     *       ],
     *       [
     *           "In-Reply-To",
     *           "<CADfEuNuk6m=RUmo+R4K65Rfskox_LOTT+pnBwTnmA_gPaf1eUQ@mail.gmail.com>"
     *       ],
     *       [
     *           "From",
     *           "Paul Werner <test@sender.example>"
     *       ],
     *       [
     *           "Date",
     *           "Mon, 18 Mar 2024 06:34:09 +0100"
     *       ],
     *       [
     *           "Message-Id",
     *           "<CADfEuNu6nBJYqNSJ-suey3a0FazJELYkNSO5JUGiiGs9hsFvGg@mail.gmail.com>"
     *       ],
     *       [
     *           "Subject",
     *           "Fwd: TEST"
     *       ],
     *       [
     *           "To",
     *           "test@domain.example"
     *       ],
     *       [
     *           "Content-Type",
     *           "multipart/related; boundary=\"00000000000022bfbe0613e8b7f5\""
     *       ]
     *   ],
     *   "stripped-html": "<html><head></head><body><div dir=\"ltr\">TESTAGAIN<img src=\"cid:ii_ltwigc770\" alt=\"Unbenannt.png\" width=\"562\" height=\"408\"><br><br></div>\n</body></html>",
     *   "stripped-text": "TESTAGAIN[image: Unbenannt.png]\r\n\r\n---------- Forwarded message ---------\r\nVon: Paul Werner <test@sender.example>\r\nDate: Mo., 18. März 2024 um 06:30 Uhr\r\nSubject: Fwd: TEST\r\nTo: <test@domain.example>\r\n\r\n\r\nHallöööö\r\n\r\n---------- Forwarded message ---------\r\nVon: Paul Werner <test@sender.example>\r\nDate: Mo., 18. März 2024 um 06:23 Uhr\r\nSubject: Fwd: TEST\r\nTo: <test@domain.example>\r\n\r\n\r\nasjkdahwdaiohdawdawdawwwww!!!\r\n\r\n---------- Forwarded message ---------\r\nVon: Paul Werner <test@sender.example>\r\nDate: Mo., 18. März 2024 um 06:22 Uhr\r\nSubject: TEST\r\nTo: <test@domain.example>\r\n\r\n\r\nTEST",
     *   "stripped-signature": ""
     * }
     * @return void
     */
    public function handle()
    {
        $from = $this->sender;//explode("|", $this->input)[0];
        $to = $this->recipient; //explode("|", $this->input)[1];
        // $messageId = explode("|", $this->input)[2]; // used as base in download function

        // Spam protection
        if ($this->engine->isInvalidOrBlocked($from, $to)) {
            return;
        }

        // lets assess this at a higher level to ensure that only valid email inboxes are processed.
        // match company
        // $company = MultiDB::findAndSetDbByExpenseMailbox($to);
        // if (!$company) {
        //     nlog('[ProcessMailgunInboundWebhook] unknown Expense Mailbox occured while handling an inbound email from mailgun: ' . $to);
        //     $this->engine->saveMeta($from, $to, true); // important to save this, to protect from spam
        //     return;
        // }

        try { // important to save meta if something fails here to prevent spam

            // fetch message from mailgun-api
            $company_mailgun_domain = $this->company->getSetting('email_sending_method') == 'client_mailgun' && strlen($this->company->getSetting('mailgun_domain') ?? '') > 2 ? $this->company->getSetting('mailgun_domain') : null;
            $company_mailgun_secret = $this->company->getSetting('email_sending_method') == 'client_mailgun' && strlen($this->company->getSetting('mailgun_secret') ?? '') > 2 ? $this->company->getSetting('mailgun_secret') : null;
            if (!($company_mailgun_domain && $company_mailgun_secret) && !(config('services.mailgun.domain') && config('services.mailgun.secret'))) {
                throw new \Error("[ProcessMailgunInboundWebhook] no mailgun credentials found, we cannot get the attachements and files");
            }

            $mail = null;
            if ($company_mailgun_domain && $company_mailgun_secret) {

                $credentials = $company_mailgun_domain . ":" . $company_mailgun_secret . "@";
                $messageUrl = $this->message_url;//explode("|", $this->input)[2];
                $messageUrl = str_replace("http://", "http://" . $credentials, $messageUrl);
                $messageUrl = str_replace("https://", "https://" . $credentials, $messageUrl);

                try {
                    $mail = json_decode(file_get_contents($messageUrl));
                } catch (\Error $e) {
                    if (config('services.mailgun.secret')) {
                        nlog("[ProcessMailgunInboundWebhook] Error while downloading with company credentials, we try to use default credentials now...");

                        $credentials = config('services.mailgun.domain') . ":" . config('services.mailgun.secret') . "@";
                        $messageUrl = $this->message_url;//explode("|", $this->input)[2];
                        $messageUrl = str_replace("http://", "http://" . $credentials, $messageUrl);
                        $messageUrl = str_replace("https://", "https://" . $credentials, $messageUrl);
                        $mail = json_decode(file_get_contents($messageUrl));

                    } else {
                        throw $e;
                    }
                }

            } else {

                $credentials = config('services.mailgun.domain') . ":" . config('services.mailgun.secret') . "@";
                $messageUrl = $this->message_url; //explode("|", $this->input)[2];
                $messageUrl = str_replace("http://", "http://" . $credentials, $messageUrl);
                $messageUrl = str_replace("https://", "https://" . $credentials, $messageUrl);
                $mail = json_decode(file_get_contents($messageUrl));

            }

            // prepare data for ingresEngine
            $inboundMail = new InboundMail();

            $inboundMail->from = $from;
            $inboundMail->to = $to; // usage of data-input, because we need a single email here
            $inboundMail->subject = $mail->Subject;
            $inboundMail->body = $mail->{"body-html"};
            $inboundMail->text_body = $mail->{"body-plain"};
            $inboundMail->date = Carbon::createFromTimeString($mail->Date);

            // parse documents as UploadedFile from webhook-data
            foreach ($mail->attachments as $attachment) { // prepare url with credentials before downloading :: https://github.com/mailgun/mailgun.js/issues/24

                // download file and save to tmp dir
                if ($company_mailgun_domain && $company_mailgun_secret) {

                    try {

                        $credentials = $company_mailgun_domain . ":" . $company_mailgun_secret . "@";
                        $url = $attachment->url;
                        $url = str_replace("http://", "http://" . $credentials, $url);
                        $url = str_replace("https://", "https://" . $credentials, $url);
                        $inboundMail->documents[] = TempFile::UploadedFileFromUrl($url, $attachment->name, $attachment->{"content-type"});

                    } catch (\Error $e) {
                        if (config('services.mailgun.secret')) {
                            nlog("[ProcessMailgunInboundWebhook] Error while downloading with company credentials, we try to use default credentials now...");

                            $credentials = config('services.mailgun.domain') . ":" . config('services.mailgun.secret') . "@";
                            $url = $attachment->url;
                            $url = str_replace("http://", "http://" . $credentials, $url);
                            $url = str_replace("https://", "https://" . $credentials, $url);
                            $inboundMail->documents[] = TempFile::UploadedFileFromUrl($url, $attachment->name, $attachment->{"content-type"});

                        } else {
                            throw $e;
                        }
                    }

                } else {

                    $credentials = config('services.mailgun.domain') . ":" . config('services.mailgun.secret') . "@";
                    $url = $attachment->url;
                    $url = str_replace("http://", "http://" . $credentials, $url);
                    $url = str_replace("https://", "https://" . $credentials, $url);
                    $inboundMail->documents[] = TempFile::UploadedFileFromUrl($url, $attachment->name, $attachment->{"content-type"});

                }

            }

        } catch (\Exception $e) {
            $this->engine->saveMeta($from, $to); // important to save this, to protect from spam
            throw $e;
        }

        // perform
        $this->engine->handleExpenseMailbox($inboundMail);
    }
}
